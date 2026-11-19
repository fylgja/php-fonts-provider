<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Api\Faces;
use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Config\Weight;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\Http\Fetcher;
use Fylgja\FontsProvider\Format;

/**
 * Fontsource publishes its catalogue as metadata rather than a stylesheet,
 * so the faces are built from the API response instead of parsed from CSS.
 */
final readonly class Fontsource implements ProviderInterface
{
    public const ID = 'fontsource';

    private const API = 'https://api.fontsource.org/v1';
    private const CDN = 'https://cdn.jsdelivr.net/fontsource/fonts';
    private const LABEL = 'Fontsource';

    /**
     * @param list<string> $downloadHosts - hosts the font files may come from
     */
    public function __construct(private array $downloadHosts = ['cdn.jsdelivr.net'])
    {
    }

    /**
     * Builds the CDN URL of a single font file.
     *
     * @param string $id
     * @param string $subset
     * @param string $style
     * @param string|null $weight - null for a variable font
     * @param string $extension
     * @return string
     */
    public static function buildFileUrl(string $id, string $subset, string $style, ?string $weight, string $extension = 'woff2'): string
    {
        return $weight === null
            ? self::CDN . "/{$id}:vf@latest/{$subset}-wght-{$style}.{$extension}"
            : self::CDN . "/{$id}@latest/{$subset}-{$weight}-{$style}.{$extension}";
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return self::LABEL;
    }

    public function isRemote(): bool
    {
        return true;
    }

    public function supportsSubsets(): bool
    {
        return true;
    }

    public function supportsVariable(): bool
    {
        return true;
    }

    public function note(): ?string
    {
        return null;
    }

    public function resolve(Family $font, ResolveContext $context): array
    {
        $id = $font->id;
        $meta = $this->getJson(
            self::API . "/fonts/{$id}",
            $context->fetcher(),
            self::LABEL . " has no font with the id \"{$id}\", derived from \"{$font->name}\".\nSearch the catalogue at https://fontsource.org/",
        );

        $list = static fn (string $key): array => is_array($meta[$key] ?? null) ? array_values($meta[$key]) : [];
        $join = static fn (array $values): string => implode(', ', array_filter($values, 'is_scalar'));
        $metaSubsets = $list('subsets');
        $metaStyles = $list('styles');
        $metaWeights = $list('weights');

        $missingSubsets = array_diff($font->subsets, $metaSubsets);
        if ($missingSubsets !== []) {
            throw new \RuntimeException("\"{$font->name}\" has no " . implode(', ', $missingSubsets) . ' subset. Available subsets: ' . $join($metaSubsets) . '.');
        }

        $missingStyles = array_diff($font->styles, $metaStyles);
        if ($missingStyles !== []) {
            throw new \RuntimeException("\"{$font->name}\" has no " . implode(', ', $missingStyles) . ' style. Available styles: ' . $join($metaStyles) . '.');
        }

        $ranges = array_filter($font->weights, static fn (Weight $weight): bool => $weight->isRange());
        if ($ranges !== [] && empty($meta['variable'])) {
            throw new \RuntimeException("\"{$font->name}\" is not a variable font, list the weights you need instead of a range. Available weights: " . $join($metaWeights) . '.');
        }

        $available = array_map('floatval', array_filter($metaWeights, 'is_numeric'));
        $missingWeights = array_filter(
            $font->weights,
            static fn (Weight $weight): bool => !$weight->isRange() && !in_array((float) $weight->api, $available, true),
        );
        if ($missingWeights !== []) {
            throw new \RuntimeException(
                "\"{$font->name}\" has no " . implode(', ', array_map(static fn (Weight $weight): string => $weight->css, $missingWeights))
                . ' weight. Available weights: ' . $join($metaWeights) . '.',
            );
        }

        // A variable file always covers the full axis, so the range it is
        // declared with comes from the font rather than from the config.
        $axis = null;
        if ($ranges !== []) {
            $variable = $this->getJson(self::API . "/variable/{$id}", $context->fetcher(), self::LABEL . " has no variable metadata for \"{$id}\".");
            $wght = $variable['axes']['wght'] ?? [];
            $axis = is_numeric($wght['min'] ?? null) && is_numeric($wght['max'] ?? null)
                ? Format::number((float) $wght['min']) . ' ' . Format::number((float) $wght['max'])
                : throw new \RuntimeException(self::LABEL . " has no weight axis for \"{$id}\".");
        }

        $faces = [];
        foreach ($font->styles as $style) {
            foreach ($font->weights as $weight) {
                foreach ($font->subsets as $subset) {
                    $range = $weight->isRange();
                    $unicodeRange = $meta['unicodeRange'][$subset] ?? null;
                    $faces[] = new Face(
                        style: $style,
                        weight: $range ? $axis : $weight->css,
                        family: is_string($meta['family'] ?? null) ? $meta['family'] : null,
                        subset: $subset,
                        unicodeRange: is_string($unicodeRange) ? $unicodeRange : null,
                        url: self::buildFileUrl($id, $subset, $style, $range ? null : $weight->api),
                    );
                }
            }
        }

        return Faces::sortFaces(
            array_values(array_filter(Faces::mergeVariableFaces($faces), [Faces::class, 'isSafe'])),
            $font->subsets,
        );
    }

    public function allowsDownload(string $url): bool
    {
        return DownloadHosts::allows($url, $this->downloadHosts);
    }

    /**
     * Fontsource serves a woff next to every static woff2. Variable fonts have
     * no woff, so those are measured on the static file of their default weight.
     */
    public function measureUrl(Family $font, Face $face, ResolveContext $context): ?string
    {
        if ($face->subset === null) {
            return null;
        }

        $weight = Format::number(Faces::measureWeight($face));

        return self::buildFileUrl($font->id, $face->subset, $face->style, $weight, 'woff');
    }

    /**
     * Reads JSON from the Fontsource API.
     *
     * @param string $url
     * @param Fetcher $fetcher
     * @param string $notFound - message thrown on a 404
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function getJson(string $url, Fetcher $fetcher, string $notFound): array
    {
        $response = $fetcher->fetch($url);

        if ($response->status === 404) {
            throw new \RuntimeException($notFound);
        }

        if (!$response->ok()) {
            throw new \RuntimeException(self::LABEL . " returned HTTP {$response->status} for {$url}.");
        }

        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(self::LABEL . " returned invalid JSON for {$url}.", 0, $exception);
        }

        return is_array($data) ? $data : [];
    }
}
