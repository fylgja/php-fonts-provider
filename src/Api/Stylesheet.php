<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Api;

use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\Http\Fetcher;

/**
 * Reading the stylesheets that Google Fonts, Bunny Fonts and Fontshare serve.
 */
final class Stylesheet
{
    /** These endpoints serve woff2 only to user agents they recognize as modern browsers. */
    public const BROWSER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** Sent when measuring, so the endpoint answers with ttf files, which need no Brotli. */
    public const PLAIN_USER_AGENT = 'fylgja-fonts-provider';

    private const FONT_FACE_REGEX = '/(?:\/\*\s*([^*]*?)\s*\*\/\s*)?@font-face\s*\{([^}]*)\}/';
    private const SOURCE_REGEX = '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)(?:\s*format\(\s*([\'"]?)([^\'")]+)\3\s*\))?/';

    /**
     * Lists the entries of a `src` declaration.
     *
     * @param string $src
     * @return list<array{url: string, format: string|null}>
     */
    public static function sources(string $src): array
    {
        preg_match_all(self::SOURCE_REGEX, $src, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [
            'url' => str_starts_with($match[2], '//') ? "https:{$match[2]}" : $match[2],
            'format' => ($match[4] ?? '') !== '' ? $match[4] : null,
        ], $matches);
    }

    /**
     * Picks the woff2 entry out of a `src` declaration.
     *
     * @param string $src
     * @return string|null absolute URL, null when there is no woff2
     */
    public static function pickWoff2(string $src): ?string
    {
        foreach (self::sources($src) as $source) {
            if ($source['format'] === 'woff2') {
                return $source['url'];
            }
        }

        return null;
    }

    /**
     * Parses the `@font-face` rules of a stylesheet.
     *
     * @param string $css
     * @param bool $subsets - read the subset from the comment above each rule
     * @param bool $anyFormat - also keep rules without a woff2, with `url` set to their first file
     * @return list<Face>
     */
    public static function parseFontFaces(string $css, bool $subsets = true, bool $anyFormat = false): array
    {
        preg_match_all(self::FONT_FACE_REGEX, $css, $matches, PREG_SET_ORDER);
        $faces = [];

        foreach ($matches as $match) {
            $declarations = [];
            foreach (explode(';', $match[2]) as $declaration) {
                $separator = strpos($declaration, ':');
                if ($separator === false) {
                    continue;
                }
                $property = strtolower(trim(substr($declaration, 0, $separator)));
                $declarations[$property] = trim(substr($declaration, $separator + 1));
            }

            $sources = isset($declarations['src']) ? self::sources($declarations['src']) : [];
            $url = isset($declarations['src']) && $declarations['src'] !== '' ? self::pickWoff2($declarations['src']) : null;
            if ($url === null && $anyFormat && $sources !== []) {
                $url = $sources[0]['url'];
            }
            if ($url === null) {
                continue;
            }

            $comment = $match[1] ?? '';
            $face = new Face(
                style: $declarations['font-style'] ?? 'normal',
                weight: $declarations['font-weight'] ?? '400',
                family: isset($declarations['font-family'])
                    ? (string) preg_replace('/^[\'"]|[\'"]$/D', '', $declarations['font-family'])
                    : null,
                stretch: $declarations['font-stretch'] ?? null,
                subset: $subsets ? ($comment !== '' ? $comment : 'all') : null,
                unicodeRange: $declarations['unicode-range'] ?? null,
                url: $url,
                sources: $sources,
            );

            if (Faces::isSafe($face)) {
                $faces[] = $face;
            }
        }

        return $faces;
    }

    /**
     * Describes why an endpoint did not return the requested font. Google
     * answers with an HTML error page, Bunny with a CSS comment and a 200.
     *
     * @param string $body
     * @return string a hint, or an empty string when the body looks fine
     */
    public static function describeFailure(string $body): string
    {
        if (preg_match('/Details:\s*([^*\n]+)/', $body, $match) && trim($match[1]) !== '') {
            return trim($match[1]);
        }

        $title = preg_match('/<title>([^<]+)<\/title>/', $body, $match) ? $match[1] : '';
        $requested = preg_match('/Requested:\s*([^<]+)/', $body, $match) ? trim($match[1]) : '';

        return implode(' - ', array_filter(
            [$title, $requested !== '' ? "Requested: {$requested}" : ''],
            static fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * Requests a stylesheet from a font endpoint and turns it into faces.
     *
     * @param Family $font
     * @param Fetcher $fetcher
     * @param string $url
     * @param string $label - the provider name, used in messages
     * @param bool $subsets - whether the endpoint serves subsets
     * @return list<Face>
     * @throws \RuntimeException when the endpoint returned nothing usable
     */
    public static function fetchFaces(Family $font, Fetcher $fetcher, string $url, string $label, bool $subsets = true): array
    {
        $response = $fetcher->fetch($url, ['user-agent' => self::BROWSER_USER_AGENT]);
        $hint = self::describeFailure($response->body);
        $suffix = $hint !== '' ? ": {$hint}" : '.';

        if (!$response->ok()) {
            throw new \RuntimeException("{$label} rejected \"{$font->name}\" (HTTP {$response->status}){$suffix}\n{$url}");
        }

        $faces = self::parseFontFaces($response->body, $subsets);

        if ($faces === []) {
            throw new \RuntimeException("{$label} returned no woff2 files for \"{$font->name}\"{$suffix}\n{$url}");
        }

        if (!$subsets) {
            return Faces::sortFaces(Faces::mergeVariableFaces($faces));
        }

        $wanted = array_values(array_filter($faces, static fn (Face $face): bool => in_array($face->subset, $font->subsets, true)));
        if ($wanted === []) {
            $available = array_values(array_unique(array_map(static fn (Face $face): string => (string) $face->subset, $faces)));
            throw new \RuntimeException(sprintf(
                '"%s" has no %s subset. Available subsets: %s.',
                $font->name,
                implode(', ', $font->subsets),
                implode(', ', $available),
            ));
        }

        return Faces::sortFaces(Faces::mergeVariableFaces($wanted), $font->subsets);
    }

    /**
     * Finds a woff or ttf to measure a face with: one the stylesheet already
     * listed next to the woff2, or else one from the same request made with a
     * user agent the endpoint answers with ttf files.
     *
     * @param Face $face
     * @param Fetcher $fetcher
     * @param string $requestUrl - the stylesheet the face came from
     * @param bool $subsets - whether the endpoint serves subsets
     * @return string|null
     */
    public static function measureUrl(Face $face, Fetcher $fetcher, string $requestUrl, bool $subsets = true): ?string
    {
        $alternate = self::measurableSource($face->sources);
        if ($alternate !== null) {
            return $alternate;
        }

        try {
            $response = $fetcher->fetch($requestUrl, ['user-agent' => self::PLAIN_USER_AGENT]);
        } catch (\Exception) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $weight = Faces::measureWeight($face);
        foreach (self::parseFontFaces($response->body, $subsets, anyFormat: true) as $candidate) {
            $weights = Faces::parseWeights($candidate->weight);
            $covers = $weights !== [] && $weight >= $weights[0] && $weight <= ($weights[1] ?? $weights[0]);
            // A response without subset comments has one file covering every subset.
            $subset = !$subsets || $candidate->subset === 'all' || $candidate->subset === $face->subset;

            if ($candidate->style === $face->style && $covers && $subset) {
                return self::measurableSource($candidate->sources);
            }
        }

        return null;
    }

    /**
     * Picks a source that is readable without Brotli.
     *
     * @param list<array{url: string, format: string|null}> $sources
     * @return string|null
     */
    private static function measurableSource(array $sources): ?string
    {
        foreach ($sources as $source) {
            if (in_array($source['format'], ['woff', 'truetype', 'opentype'], true)) {
                return $source['url'];
            }
        }

        return null;
    }
}
