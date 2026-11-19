<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

use Fylgja\FontsProvider\Provider\ProviderInterface;
use Fylgja\FontsProvider\Provider\ProviderRegistry;

/**
 * Validates the font families and fills in the defaults, without any request,
 * so a form can show every error before anything is fetched.
 */
final class FontConfig
{
    public const DEFAULT_STYLES = ['normal'];
    public const DEFAULT_WEIGHTS = ['400'];
    public const DEFAULT_SUBSETS = ['latin'];
    public const DEFAULT_DISPLAY = 'swap';
    public const DEFAULT_FALLBACKS = ['sans-serif'];

    public const DISPLAY_VALUES = ['auto', 'block', 'swap', 'fallback', 'optional'];

    /** Characters that would let a value break out of a CSS string or declaration. */
    private const UNSAFE = '/["\\\\<>{};\x00-\x1f\x7f]/';

    /** Values a provider or variant puts in a declaration as is, such as `font-stretch` or `unicode-range`. */
    public const SAFE_VALUE = '/^[A-Za-z0-9 ,.%+?\-]*$/D';

    /**
     * Validates and normalizes a list of font families.
     *
     * @param array<mixed> $fonts
     * @param ProviderRegistry|null $providers - defaults to the built in providers
     * @return NormalizedConfig
     */
    public static function normalize(array $fonts, ?ProviderRegistry $providers = null): NormalizedConfig
    {
        $providers ??= ProviderRegistry::default();

        if (!array_is_list($fonts)) {
            return new NormalizedConfig([], ['The fonts must be a list of font families.']);
        }

        $errors = [];
        $normalized = [];
        foreach ($fonts as $index => $font) {
            $family = self::normalizeFont($font, "fonts[{$index}]", $errors, $providers);
            if ($family !== null) {
                $normalized[] = $family;
            }
        }

        $seen = [];
        foreach ($normalized as $family) {
            if (isset($seen[$family->cssVariable])) {
                $errors[] = "Duplicate cssVariable \"{$family->cssVariable}\", set a unique cssVariable for \"{$family->name}\".";
            }
            $seen[$family->cssVariable] = true;
        }

        return new NormalizedConfig($normalized, $errors);
    }

    /**
     * Validates and fills in the defaults for a single family.
     *
     * @param mixed $font
     * @param string $label - used in error messages
     * @param list<string> $errors - collects the errors
     * @param ProviderRegistry $providers
     * @return Family|null null when invalid
     */
    private static function normalizeFont(mixed $font, string $label, array &$errors, ProviderRegistry $providers): ?Family
    {
        if (!self::isMap($font)) {
            $errors[] = "{$label} must be an array of options.";
            return null;
        }

        $count = count($errors);
        $providerId = self::string($font, 'provider', $label, $errors) ?? $providers->defaultId();
        $name = self::string($font, 'name', $label, $errors) ?? '';
        $cssVariable = self::string($font, 'cssVariable', $label, $errors) ?? '';
        $cssSelector = self::string($font, 'cssSelector', $label, $errors) ?? '';
        $id = self::string($font, 'id', $label, $errors) ?? '';
        $display = self::string($font, 'display', $label, $errors) ?? self::DEFAULT_DISPLAY;
        $fallbacks = self::strings($font, 'fallbacks', $label, $errors) ?: self::DEFAULT_FALLBACKS;
        $preload = self::bool($font, 'preload', $label, $errors) ?? false;
        $adjustFallback = self::bool($font, 'adjustFallback', $label, $errors) ?? true;

        $provider = $providers->get($providerId);
        if ($provider === null) {
            $errors[] = sprintf('%s.provider "%s" is not supported, use one of: %s.', $label, $providerId, implode(', ', $providers->ids()));
            return null;
        }

        if ($name === '') {
            $errors[] = "{$label}.name is required.";
        }

        $slug = Slug::slugify($name);
        $base = [
            'provider' => $provider->id(),
            'name' => $name,
            'cssVariable' => $cssVariable === ''
                ? "--font-{$slug}"
                : (str_starts_with($cssVariable, '--') ? $cssVariable : "--{$cssVariable}"),
            'cssSelector' => $cssSelector !== '' ? $cssSelector : null,
            'preload' => $preload,
            'adjustFallback' => $adjustFallback,
            'slug' => $slug,
            // Providers that address a family by an id in a URL derive it from the
            // name, which `id` overrides for the rare family whose id differs.
            'id' => $id !== '' ? $id : $slug,
            'display' => $display,
            'fallbacks' => $fallbacks,
        ];

        $family = $provider->isRemote()
            ? self::normalizeRemote($font, $label, $base, $provider, $errors)
            : self::normalizeLocal($font, $label, $base, $errors);

        if ($family === null || count($errors) > $count || !self::isSafe($family, $label, $errors)) {
            return null;
        }

        return $family;
    }

    /**
     * Fills in the variants of the local provider.
     *
     * @param array<string, mixed> $font
     * @param string $label
     * @param array<string, mixed> $base - the shared family fields
     * @param list<string> $errors
     * @return Family|null
     */
    private static function normalizeLocal(array $font, string $label, array $base, array &$errors): ?Family
    {
        $variants = $font['variants'] ?? [];
        if (!is_array($variants) || !array_is_list($variants) || $variants === []) {
            $errors[] = "{$label}.variants is required for the \"local\" provider, and must list at least one variant.";
            return null;
        }

        $normalized = [];
        foreach ($variants as $index => $variant) {
            $variantLabel = "{$label}.variants[{$index}]";
            if (!self::isMap($variant)) {
                $errors[] = "{$variantLabel} must be an array of options.";
                continue;
            }

            $count = count($errors);
            $src = self::strings($variant, 'src', $variantLabel, $errors) ?? [];
            $weight = self::weights($variant, 'weight', $variantLabel, $errors)[0] ?? Weight::normalize(self::DEFAULT_WEIGHTS[0]);
            $style = self::string($variant, 'style', $variantLabel, $errors) ?? self::DEFAULT_STYLES[0];
            $stretch = self::string($variant, 'stretch', $variantLabel, $errors);
            $unicodeRange = self::string($variant, 'unicodeRange', $variantLabel, $errors);
            $display = self::string($variant, 'display', $variantLabel, $errors) ?? $base['display'];
            if (count($errors) > $count) {
                continue;
            }

            if ($src === []) {
                $errors[] = "{$variantLabel}.src is required.";
                continue;
            }

            $unknown = array_filter($src, static fn (string $file): bool => FontFormat::fromFile($file) === null);
            if ($unknown !== []) {
                $errors[] = "{$variantLabel}.src has files with an unknown font format: " . implode(', ', $unknown) . '.';
                continue;
            }

            $normalized[] = new LocalVariant($weight, $style, $stretch, $unicodeRange, $display, $src);
        }

        return count($normalized) === count($variants) ? new Family(...$base, variants: $normalized) : null;
    }

    /**
     * Fills in the styles, weights and subsets of a remote provider.
     *
     * @param array<string, mixed> $font
     * @param string $label
     * @param array<string, mixed> $base - the shared family fields
     * @param ProviderInterface $provider
     * @param list<string> $errors
     * @return Family|null
     */
    private static function normalizeRemote(
        array $font,
        string $label,
        array $base,
        ProviderInterface $provider,
        array &$errors,
    ): ?Family {
        $count = count($errors);
        $styles = self::strings($font, 'styles', $label, $errors) ?: self::DEFAULT_STYLES;
        $weights = self::weights($font, 'weights', $label, $errors) ?: [Weight::normalize(self::DEFAULT_WEIGHTS[0])];
        $subsets = $provider->supportsSubsets() ? (self::strings($font, 'subsets', $label, $errors) ?: self::DEFAULT_SUBSETS) : [];
        if (count($errors) > $count) {
            return null;
        }

        $invalidStyles = array_diff($styles, ['normal', 'italic']);
        if ($invalidStyles !== []) {
            $errors[] = "{$label}.styles only supports \"normal\" and \"italic\", received: " . implode(', ', $invalidStyles) . '.';
            return null;
        }

        $weights = Weight::sort($weights);
        $overlap = Weight::findOverlap($weights);
        if ($overlap !== null) {
            $errors[] = "{$label}.weights lists \"{$overlap[1]->css}\" and \"{$overlap[0]->css}\", which cover the same weight. Use either the range or the single weights.";
            return null;
        }

        $ranges = array_filter($weights, static fn (Weight $weight): bool => $weight->isRange());
        if ($ranges !== [] && !$provider->supportsVariable()) {
            $errors[] = sprintf(
                '%s.weights uses the range %s, which %s does not serve. %s',
                $label,
                implode(', ', array_map(static fn (Weight $weight): string => "\"{$weight->css}\"", $ranges)),
                $provider->label(),
                $provider->note() ?? '',
            );
            return null;
        }

        return new Family(...$base, styles: array_values(array_unique($styles)), weights: $weights, subsets: array_values(array_unique($subsets)));
    }

    /**
     * Rejects values that would break out of a stylesheet, a file name or a URL.
     *
     * @param Family $family
     * @param string $label
     * @param list<string> $errors
     * @return bool
     */
    private static function isSafe(Family $family, string $label, array &$errors): bool
    {
        $count = count($errors);

        if (preg_match(self::UNSAFE, $family->name)) {
            $errors[] = "{$label}.name contains characters that are not allowed in a font family name.";
        }

        if (!preg_match('/^--[A-Za-z0-9_-]+$/D', $family->cssVariable)) {
            $errors[] = "{$label}.cssVariable \"{$family->cssVariable}\" is not a valid custom property name.";
        }

        if ($family->cssSelector !== null && preg_match('#[{};<>\x00-\x1f\x7f]|/\*#', $family->cssSelector)) {
            $errors[] = "{$label}.cssSelector contains characters that are not allowed in a selector.";
        }

        if (!preg_match('/^[A-Za-z0-9_-]*$/D', $family->id)) {
            $errors[] = "{$label}.id \"{$family->id}\" may only contain letters, digits, \"-\" and \"_\".";
        }

        if (!in_array($family->display, self::DISPLAY_VALUES, true)) {
            $errors[] = "{$label}.display must be one of: " . implode(', ', self::DISPLAY_VALUES) . '.';
        }

        foreach ($family->fallbacks as $fallback) {
            if (!preg_match('/^(?:[A-Za-z0-9 _-]+|"[A-Za-z0-9 _.-]+"|\'[A-Za-z0-9 _.-]+\')$/D', $fallback)) {
                $errors[] = "{$label}.fallbacks has a value that is not a font family name: {$fallback}.";
            }
        }

        foreach ($family->weights as $weight) {
            if (!preg_match('/^\d+(?:\.\.\d+)?$/D', $weight->api)) {
                $errors[] = "{$label}.weights has \"{$weight->css}\", which is not a number or a range of numbers.";
            }
        }

        foreach ($family->subsets as $subset) {
            if (!preg_match('/^[A-Za-z0-9_\-\[\]]+$/D', $subset)) {
                $errors[] = "{$label}.subsets has \"{$subset}\", which is not a valid subset name.";
            }
        }

        foreach ($family->variants as $index => $variant) {
            $variantLabel = "{$label}.variants[{$index}]";
            if (!preg_match('/^\d+(?: \d+)?$/D', $variant->weight->css)) {
                $errors[] = "{$variantLabel}.weight \"{$variant->weight->css}\" is not a number or a range of numbers.";
            }
            foreach (['style' => $variant->style, 'stretch' => $variant->stretch, 'unicodeRange' => $variant->unicodeRange] as $key => $value) {
                if ($value !== null && !preg_match(self::SAFE_VALUE, $value)) {
                    $errors[] = "{$variantLabel}.{$key} contains characters that are not allowed.";
                }
            }
            if (!in_array($variant->display, self::DISPLAY_VALUES, true)) {
                $errors[] = "{$variantLabel}.display must be one of: " . implode(', ', self::DISPLAY_VALUES) . '.';
            }
            foreach ($variant->src as $file) {
                if (preg_match('/["\\\\<>\x00-\x1f\x7f]/', $file) || preg_match('#(^|/)\.\.(/|$)#', $file) || str_starts_with($file, '/')) {
                    $errors[] = "{$variantLabel}.src \"{$file}\" must be a path inside the fonts folder.";
                }
            }
        }

        return count($errors) === $count;
    }

    /**
     * Reads an optional string, trimmed.
     *
     * @param array<string, mixed> $options
     * @param string $key
     * @param string $label
     * @param list<string> $errors
     * @return string|null null when absent
     */
    private static function string(array $options, string $key, string $label, array &$errors): ?string
    {
        $value = $options[$key] ?? null;
        if ($value === null || is_string($value)) {
            return $value === null ? null : trim($value);
        }

        $errors[] = "{$label}.{$key} must be a string.";
        return null;
    }

    /**
     * Reads an optional list of strings, trimmed and without empty values.
     *
     * @param array<string, mixed> $options
     * @param string $key
     * @param string $label
     * @param list<string> $errors
     * @return list<string>|null null when absent
     */
    private static function strings(array $options, string $key, string $label, array &$errors): ?array
    {
        $value = $options[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value) || array_filter($value, 'is_string') !== $value) {
            $errors[] = "{$label}.{$key} must be a list of strings.";
            return null;
        }

        return array_values(array_filter(array_map('trim', $value), static fn (string $item): bool => $item !== ''));
    }

    /**
     * Reads an optional weight or list of weights, each a number or a range.
     *
     * @param array<string, mixed> $options
     * @param string $key
     * @param string $label
     * @param list<string> $errors
     * @return list<Weight>
     */
    private static function weights(array $options, string $key, string $label, array &$errors): array
    {
        $value = $options[$key] ?? [];
        $list = is_array($value) ? $value : [$value];

        if (!array_is_list($list) || array_filter($list, static fn (mixed $item): bool => is_int($item) || is_string($item)) !== $list) {
            $errors[] = "{$label}.{$key} must be a number, a range such as \"100 900\", or a list of those.";
            return [];
        }

        return array_map([Weight::class, 'normalize'], $list);
    }

    /**
     * Reads an optional boolean.
     *
     * @param array<string, mixed> $options
     * @param string $key
     * @param string $label
     * @param list<string> $errors
     * @return bool|null null when absent
     */
    private static function bool(array $options, string $key, string $label, array &$errors): ?bool
    {
        $value = $options[$key] ?? null;
        if ($value === null || is_bool($value)) {
            return $value;
        }

        $errors[] = "{$label}.{$key} must be true or false.";
        return null;
    }

    /**
     * Whether a value is an array of named options rather than a list.
     *
     * @param mixed $value
     * @return bool
     */
    private static function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }
}
