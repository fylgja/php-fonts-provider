<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Api\Faces;
use Fylgja\FontsProvider\Api\Stylesheet;
use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\Format;

/**
 * Fontshare serves one static file per family covering every character it has.
 */
final readonly class Fontshare implements ProviderInterface
{
    public const ID = 'fontshare';

    private const ENDPOINT = 'https://api.fontshare.com/v2/css';
    private const LABEL = 'Fontshare';

    /**
     * @param list<string> $downloadHosts - hosts the font files may come from
     */
    public function __construct(private array $downloadHosts = ['cdn.fontshare.com'])
    {
    }

    /**
     * Builds the request URL. Fontshare identifies a variant by a number where
     * the italic of a weight is that weight plus one, so 401 is italic 400.
     *
     * @param Family $font
     * @return string
     */
    public function buildRequestUrl(Family $font): string
    {
        $variants = [];
        foreach ($font->styles as $style) {
            foreach ($font->weights as $weight) {
                $variants[] = (int) $weight->api + ($style === 'italic' ? 1 : 0);
            }
        }
        $variants = array_values(array_unique($variants));
        sort($variants);

        return self::ENDPOINT . "?f[]={$font->id}@" . implode(',', $variants);
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
        return false;
    }

    public function supportsVariable(): bool
    {
        return false;
    }

    public function note(): ?string
    {
        return 'Fontshare serves static files only, list the weights you need instead of a range.';
    }

    public function resolve(Family $font, ResolveContext $context): array
    {
        $faces = Stylesheet::fetchFaces($font, $context->fetcher(), $this->buildRequestUrl($font), self::LABEL, false);
        $this->assertComplete($font, $faces);

        return $faces;
    }

    public function allowsDownload(string $url): bool
    {
        return DownloadHosts::allows($url, $this->downloadHosts);
    }

    public function measureUrl(Family $font, Face $face, ResolveContext $context): ?string
    {
        return Stylesheet::measureUrl($face, $context->fetcher(), $this->buildRequestUrl($font), false);
    }

    /**
     * Fontshare drops variants it does not have instead of failing, so the
     * response is checked against what was asked for.
     *
     * @param Family $font
     * @param list<Face> $faces
     * @return void
     * @throws \RuntimeException when a variant is missing
     */
    private function assertComplete(Family $font, array $faces): void
    {
        $found = [];
        foreach ($faces as $face) {
            $weight = Faces::parseWeights($face->weight)[0] ?? 0.0;
            $found[Format::number($weight) . "-{$face->style}"] = true;
        }

        $missing = [];
        foreach ($font->styles as $style) {
            foreach ($font->weights as $weight) {
                if (!isset($found["{$weight->api}-{$style}"])) {
                    $missing[] = "{$weight->css} {$style}";
                }
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                self::LABEL . ' has no ' . implode(', ', $missing) . " for \"{$font->name}\".\n" . $this->buildRequestUrl($font),
            );
        }
    }
}
