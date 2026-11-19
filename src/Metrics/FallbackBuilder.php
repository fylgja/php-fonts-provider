<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Metrics;

use Fylgja\FontsProvider\Format;

final class FallbackBuilder
{
    /**
     * Metrics of the fonts a visitor is likely to already have, measured with
     * the same code that measures a webfont so the two are comparable. Each
     * lists the families that are metrically compatible with it.
     */
    private const SYSTEM_FONTS = [
        'arial' => [
            'local' => ['Arial', 'Liberation Sans', 'Arimo'],
            'unitsPerEm' => 2048, 'ascent' => 1854, 'descent' => -434, 'lineGap' => 67, 'xWidthAvg' => 904,
        ],
        'times new roman' => [
            'local' => ['Times New Roman', 'Liberation Serif', 'Tinos'],
            'unitsPerEm' => 2048, 'ascent' => 1825, 'descent' => -443, 'lineGap' => 87, 'xWidthAvg' => 819,
        ],
        'courier new' => [
            'local' => ['Courier New', 'Liberation Mono', 'Cousine'],
            'unitsPerEm' => 2048, 'ascent' => 1705, 'descent' => -615, 'lineGap' => 0, 'xWidthAvg' => 1229,
        ],
        'georgia' => [
            'local' => ['Georgia', 'Gelasio'],
            'unitsPerEm' => 2048, 'ascent' => 1878, 'descent' => -449, 'lineGap' => 0, 'xWidthAvg' => 899,
        ],
        'verdana' => [
            'local' => ['Verdana', 'DejaVu Sans'],
            'unitsPerEm' => 2048, 'ascent' => 2059, 'descent' => -430, 'lineGap' => 0, 'xWidthAvg' => 1041,
        ],
        'tahoma' => [
            'local' => ['Tahoma'],
            'unitsPerEm' => 2048, 'ascent' => 2049, 'descent' => -423, 'lineGap' => 0, 'xWidthAvg' => 912,
        ],
        'trebuchet ms' => [
            'local' => ['Trebuchet MS'],
            'unitsPerEm' => 2048, 'ascent' => 1923, 'descent' => -455, 'lineGap' => 0, 'xWidthAvg' => 929,
        ],
    ];

    /** The font a generic family name stands in for when nothing else is named. */
    private const GENERIC_FONTS = [
        'sans-serif' => 'arial',
        'ui-sans-serif' => 'arial',
        'system-ui' => 'arial',
        'serif' => 'times new roman',
        'ui-serif' => 'times new roman',
        'monospace' => 'courier new',
        'ui-monospace' => 'courier new',
    ];

    /**
     * Chooses which of a family's fallbacks to adjust. A named font wins over
     * a generic one, since that is what the visitor will actually see.
     *
     * @param list<string> $fallbacks - the configured font stack, without the family
     * @return array{local: list<string>, unitsPerEm: int, ascent: int, descent: int, lineGap: int, xWidthAvg: int}|null
     */
    public static function pickFallback(array $fallbacks): ?array
    {
        foreach ($fallbacks as $name) {
            $key = (string) preg_replace('/^[\'"]|[\'"]$/D', '', strtolower($name));
            if (isset(self::SYSTEM_FONTS[$key])) {
                return self::SYSTEM_FONTS[$key];
            }
        }

        foreach ($fallbacks as $name) {
            $generic = self::GENERIC_FONTS[strtolower($name)] ?? null;
            if ($generic !== null) {
                return self::SYSTEM_FONTS[$generic];
            }
        }

        return null;
    }

    /**
     * Builds the adjusted fallback face of a family.
     *
     * @param string $name - the family name
     * @param list<string> $fallbacks
     * @param Metrics $metrics - of the webfont
     * @return FallbackFace|null null when no fallback is known
     */
    public static function build(string $name, array $fallbacks, Metrics $metrics): ?FallbackFace
    {
        $fallback = self::pickFallback($fallbacks);
        if ($fallback === null || $metrics->unitsPerEm <= 0 || $metrics->xWidthAvg <= 0) {
            return null;
        }

        // How much wider or narrower the webfont is than the font standing in
        // for it. The overrides apply to the already scaled font.
        $sizeAdjust = $metrics->xWidthAvg / $metrics->unitsPerEm / ($fallback['xWidthAvg'] / $fallback['unitsPerEm']);
        $scaled = $metrics->unitsPerEm * $sizeAdjust;

        return new FallbackFace(
            family: "{$name} fallback",
            local: $fallback['local'],
            sizeAdjust: Format::percent($sizeAdjust),
            ascentOverride: Format::percent($metrics->ascent / $scaled),
            descentOverride: Format::percent(abs($metrics->descent) / $scaled),
            lineGapOverride: Format::percent($metrics->lineGap / $scaled),
        );
    }

}
