<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Metrics;

/**
 * The vertical metrics and average character width of a font, in font units.
 */
final readonly class Metrics
{
    /**
     * @param int $unitsPerEm
     * @param int $ascent
     * @param int $descent - negative below the baseline
     * @param int $lineGap
     * @param int $xWidthAvg - English letter frequency weighted average advance
     */
    public function __construct(
        public int $unitsPerEm,
        public int $ascent,
        public int $descent,
        public int $lineGap,
        public int $xWidthAvg,
    ) {
    }

    /**
     * @return array{unitsPerEm: int, ascent: int, descent: int, lineGap: int, xWidthAvg: int}
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
