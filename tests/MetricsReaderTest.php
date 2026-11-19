<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\Metrics\MetricsReader;

final class MetricsReaderTest extends TestCase
{
    /** The same font as a woff2, woff and ttf. */
    private const PACIFICO = ['unitsPerEm' => 1000, 'ascent' => 1303, 'descent' => -453, 'lineGap' => 0, 'xWidthAvg' => 408];

    public function testReadsWoffLikeTheWoff2(): void
    {
        $reader = new MetricsReader(false);

        self::assertSame(self::PACIFICO, $reader->read(self::fixtureFont('substitute.woff'))?->toArray());
        self::assertSame(self::PACIFICO, $reader->read(self::fixtureFont('substitute.ttf'))?->toArray());
    }

    public function testCannotReadWoff2WithoutBrotli(): void
    {
        $reader = new MetricsReader(false);
        $woff2 = self::fixtureFont('substitute.woff2');

        self::assertFalse($reader->canRead($woff2));
        self::assertNull($reader->read($woff2));
        self::assertTrue($reader->canRead(self::fixtureFont('substitute.woff')));
    }

    public function testReadsWoff2WithBrotli(): void
    {
        if (!function_exists('brotli_uncompress')) {
            self::markTestSkipped('Needs ext-brotli.');
        }

        self::assertSame(self::PACIFICO, (new MetricsReader())->read(self::fixtureFont('substitute.woff2'))?->toArray());
    }

    public function testReturnsNullForBrokenInput(): void
    {
        $reader = new MetricsReader(false);
        $ttf = self::fixtureFont('substitute.ttf');

        self::assertNull($reader->read(''));
        self::assertNull($reader->read('not a font at all'));
        self::assertNull($reader->read(substr($ttf, 0, 400)));
        self::assertNull($reader->read(substr(self::fixtureFont('substitute.woff'), 0, 400)));
    }
}
