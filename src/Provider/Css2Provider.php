<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Api\Stylesheet;
use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Config\Weight;
use Fylgja\FontsProvider\Face;

/**
 * A provider backed by the CSS2 API, which Google Fonts introduced and Bunny
 * Fonts implements as a drop in replacement.
 */
final readonly class Css2Provider implements ProviderInterface
{
    /**
     * @param string $id
     * @param string $label
     * @param string $endpoint
     * @param bool $variable - whether the endpoint serves variable fonts
     * @param string|null $note - extra guidance shown with a validation error
     * @param list<string> $downloadHosts - hosts the font files may come from
     */
    public function __construct(
        private string $id,
        private string $label,
        private string $endpoint,
        private bool $variable,
        private ?string $note,
        private array $downloadHosts,
    ) {
    }

    /**
     * @return self
     */
    public static function googleFonts(): self
    {
        return new self('google-fonts', 'Google Fonts', 'https://fonts.googleapis.com/css2', true, null, ['fonts.gstatic.com']);
    }

    /**
     * Bunny mirrors the Google Fonts catalogue as static files, so a weight
     * range would be one file per weight step instead of one axis.
     *
     * @return self
     */
    public static function bunnyFonts(): self
    {
        return new self(
            'bunny-fonts',
            'Bunny Fonts',
            'https://fonts.bunny.net/css2',
            false,
            'Bunny Fonts serves static files only, list the weights you need instead of a range.',
            ['fonts.bunny.net', 'cdn.jsdelivr.net'],
        );
    }

    /**
     * Builds the request URL.
     *
     * @param Family $font
     * @return string
     */
    public function buildRequestUrl(Family $font): string
    {
        $family = implode('+', array_map(
            'rawurlencode',
            preg_split('/\s+/', trim($font->name)),
        ));
        $weights = array_map(static fn (Weight $weight): string => $weight->api, $font->weights);

        if (in_array('italic', $font->styles, true)) {
            $tuples = [];
            if (in_array('normal', $font->styles, true)) {
                foreach ($weights as $weight) {
                    $tuples[] = "0,{$weight}";
                }
            }
            foreach ($weights as $weight) {
                $tuples[] = "1,{$weight}";
            }
            $axis = 'ital,wght@' . implode(';', $tuples);
        } else {
            $axis = 'wght@' . implode(';', $weights);
        }

        return "{$this->endpoint}?family={$family}:{$axis}&display={$font->display}";
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
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
        return $this->variable;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function resolve(Family $font, ResolveContext $context): array
    {
        return Stylesheet::fetchFaces($font, $context->fetcher(), $this->buildRequestUrl($font), $this->label);
    }

    public function allowsDownload(string $url): bool
    {
        return DownloadHosts::allows($url, $this->downloadHosts);
    }

    public function measureUrl(Family $font, Face $face, ResolveContext $context): ?string
    {
        return Stylesheet::measureUrl($face, $context->fetcher(), $this->buildRequestUrl($font));
    }
}
