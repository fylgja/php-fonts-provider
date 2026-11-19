<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Metrics;

/**
 * A `@font-face` that makes a locally installed font take up the same space
 * as the webfont, so swapping one for the other does not move the page.
 */
final readonly class FallbackFace
{
    /**
     * @param string $family - "<name> fallback"
     * @param list<string> $local - metrically compatible local font names
     * @param string $sizeAdjust - percentages, such as "92.43%"
     * @param string $ascentOverride
     * @param string $descentOverride
     * @param string $lineGapOverride
     */
    public function __construct(
        public string $family,
        public array $local,
        public string $sizeAdjust,
        public string $ascentOverride,
        public string $descentOverride,
        public string $lineGapOverride,
    ) {
    }
}
