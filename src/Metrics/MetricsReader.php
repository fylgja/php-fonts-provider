<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Metrics;

use Closure;

/**
 * Reads the metrics a font declares about itself, so a fallback can be
 * adjusted to occupy the same space and the page does not shift when the
 * webfont arrives. Reads ttf, otf and woff, and woff2 when Brotli is available.
 */
final readonly class MetricsReader
{
    /** The 63 tags a woff2 table directory can refer to by index, in spec order. */
    private const KNOWN_TAGS = [
        'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post',
        'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ', 'VORG', 'EBDT',
        'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea',
        'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH',
        'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar',
        'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar',
        'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop',
        'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill',
    ];

    /**
     * How often each character appears in English, used to weight the average
     * character width. The OS/2 table has a field for this, but fonts disagree
     * on what it means, so it is measured from the advance widths instead.
     */
    private const WEIGHTINGS = [
        'a' => 0.0668, 'b' => 0.0122, 'c' => 0.0228, 'd' => 0.0348, 'e' => 0.1039, 'f' => 0.0182,
        'g' => 0.0165, 'h' => 0.0499, 'i' => 0.057, 'j' => 0.001, 'k' => 0.0063, 'l' => 0.033,
        'm' => 0.0197, 'n' => 0.0552, 'o' => 0.0614, 'p' => 0.0158, 'q' => 0.0008, 'r' => 0.049,
        's' => 0.0518, 't' => 0.0741, 'u' => 0.0226, 'v' => 0.008, 'w' => 0.0193, 'x' => 0.0015,
        'y' => 0.0161, 'z' => 0.0007, ' ' => 0.1818,
    ];

    /** @var (Closure(string): (string|false))|null */
    private ?Closure $brotli;

    /**
     * @param (callable(string): (string|false))|false|null $brotli - decompresses a Brotli stream,
     *        null uses `brotli_uncompress()` when ext-brotli is loaded, false reads no woff2
     */
    public function __construct(callable|false|null $brotli = null)
    {
        $this->brotli = match (true) {
            $brotli === false => null,
            $brotli === null => function_exists('brotli_uncompress') ? brotli_uncompress(...) : null,
            default => $brotli(...),
        };
    }

    /**
     * Whether the bytes can be read, false for a woff2 without Brotli.
     *
     * @param string $bytes
     * @return bool
     */
    public function canRead(string $bytes): bool
    {
        return $this->readsWoff2() || !str_starts_with($bytes, 'wOF2');
    }

    /**
     * Whether woff2 files can be read, which needs Brotli.
     *
     * @return bool
     */
    public function readsWoff2(): bool
    {
        return $this->brotli !== null;
    }

    /**
     * Reads the metrics of a woff2, woff, ttf or otf.
     *
     * @param string $bytes
     * @return Metrics|null null when the font cannot be read or lacks a glyph for a..z
     */
    public function read(string $bytes): ?Metrics
    {
        try {
            $tables = match (substr($bytes, 0, 4)) {
                'wOF2' => $this->readWoff2($bytes),
                'wOFF' => $this->readWoff($bytes),
                default => $this->readSfnt($bytes),
            };

            if ($tables === null || !isset($tables['head'], $tables['hhea'])) {
                return null;
            }

            $head = $tables['head'];
            $hhea = $tables['hhea'];
            $os2 = $tables['OS/2'] ?? null;

            // A font sets USE_TYPO_METRICS when its OS/2 typo values are the ones
            // it wants line boxes built from, otherwise hhea is authoritative.
            $useTypo = $os2 !== null && (Binary::uint16($os2, 62) & (1 << 7));
            $xWidthAvg = $this->averageWidth($tables);
            if (!$xWidthAvg) {
                return null;
            }

            return new Metrics(
                unitsPerEm: Binary::uint16($head, 18),
                ascent: $useTypo ? Binary::int16($os2, 68) : Binary::int16($hhea, 4),
                descent: $useTypo ? Binary::int16($os2, 70) : Binary::int16($hhea, 6),
                lineGap: $useTypo ? Binary::int16($os2, 72) : Binary::int16($hhea, 8),
                xWidthAvg: $xWidthAvg,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Splits a woff2 into its tables. Everything is one Brotli stream, and the
     * directory gives the length of each table within it.
     *
     * @param string $bytes
     * @return array<string, string>|null null without Brotli
     */
    private function readWoff2(string $bytes): ?array
    {
        if ($this->brotli === null) {
            return null;
        }

        $numTables = Binary::uint16($bytes, 12);
        $totalCompressedSize = Binary::uint32($bytes, 20);
        $entries = [];
        $pos = 48;

        for ($i = 0; $i < $numTables; $i++) {
            $flags = Binary::uint8($bytes, $pos++);
            $index = $flags & 0x3f;

            if ($index === 63) {
                $tag = Binary::tag($bytes, $pos);
                $pos += 4;
            } else {
                $tag = trim(self::KNOWN_TAGS[$index]);
            }

            [$length, $pos] = self::readBase128($bytes, $pos);

            $version = $flags >> 6;
            $transformed = $tag === 'glyf' || $tag === 'loca' ? $version === 0 : $version !== 0;
            if ($transformed) {
                [$length, $pos] = self::readBase128($bytes, $pos);
            }

            $entries[] = ['tag' => $tag, 'length' => $length, 'transformed' => $transformed];
        }

        $data = ($this->brotli)(Binary::sub($bytes, $pos, $totalCompressedSize));
        if (!is_string($data)) {
            return null;
        }

        $tables = [];
        $offset = 0;
        foreach ($entries as $entry) {
            // A transformed table is not in the layout this reads, so it is skipped.
            if (!$entry['transformed']) {
                $tables[$entry['tag']] = Binary::sub($data, $offset, $entry['length']);
            }
            $offset += $entry['length'];
        }

        return $tables;
    }

    /**
     * Splits a woff into its tables, each zlib compressed when that made it smaller.
     *
     * @param string $bytes
     * @return array<string, string>|null
     */
    private function readWoff(string $bytes): ?array
    {
        $numTables = Binary::uint16($bytes, 12);
        $tables = [];

        for ($i = 0; $i < $numTables; $i++) {
            $entry = 44 + $i * 20;
            $tag = Binary::tag($bytes, $entry);
            $offset = Binary::uint32($bytes, $entry + 4);
            $compLength = Binary::uint32($bytes, $entry + 8);
            $origLength = Binary::uint32($bytes, $entry + 12);
            $data = Binary::sub($bytes, $offset, $compLength);

            if ($compLength < $origLength) {
                $data = @gzuncompress($data);
                if ($data === false) {
                    return null;
                }
            }

            $tables[$tag] = $data;
        }

        return $tables;
    }

    /**
     * Splits a plain TrueType or OpenType font into its tables.
     *
     * @param string $bytes
     * @return array<string, string>
     */
    private function readSfnt(string $bytes): array
    {
        $numTables = Binary::uint16($bytes, 4);
        $tables = [];

        for ($i = 0; $i < $numTables; $i++) {
            $entry = 12 + $i * 16;
            $tag = Binary::tag($bytes, $entry);
            $offset = Binary::uint32($bytes, $entry + 8);
            $tables[$tag] = Binary::sub($bytes, $offset, Binary::uint32($bytes, $entry + 12));
        }

        return $tables;
    }

    /**
     * Reads a UIntBase128, the variable length integer of a woff2 directory.
     *
     * @param string $bytes
     * @param int $pos
     * @return array{int, int} the value and the position after it
     */
    private static function readBase128(string $bytes, int $pos): array
    {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $byte = Binary::uint8($bytes, $pos++);
            $value = (($value << 7) | ($byte & 0x7f)) & 0xffffffff;
            if (($byte & 0x80) === 0) {
                return [$value, $pos];
            }
        }

        throw new \RangeException('Invalid UIntBase128');
    }

    /**
     * Looks a code point up in a format 4 cmap subtable.
     *
     * @param string $table
     * @param int $code
     * @return int glyph id, 0 when absent
     */
    private static function lookupFormat4(string $table, int $code): int
    {
        $segCount = intdiv(Binary::uint16($table, 6), 2);
        $end = 14;
        $start = $end + $segCount * 2 + 2;
        $delta = $start + $segCount * 2;
        $range = $delta + $segCount * 2;

        for ($i = 0; $i < $segCount; $i++) {
            if (Binary::uint16($table, $end + $i * 2) < $code) {
                continue;
            }
            if (Binary::uint16($table, $start + $i * 2) > $code) {
                return 0;
            }

            $rangeOffset = Binary::uint16($table, $range + $i * 2);
            if ($rangeOffset === 0) {
                return ($code + Binary::int16($table, $delta + $i * 2)) & 0xffff;
            }

            $at = $range + $i * 2 + $rangeOffset + ($code - Binary::uint16($table, $start + $i * 2)) * 2;
            $glyph = Binary::uint16($table, $at);

            return $glyph === 0 ? 0 : ($glyph + Binary::int16($table, $delta + $i * 2)) & 0xffff;
        }

        return 0;
    }

    /**
     * Looks a code point up in a format 12 cmap subtable.
     *
     * @param string $table
     * @param int $code
     * @return int glyph id, 0 when absent
     */
    private static function lookupFormat12(string $table, int $code): int
    {
        $groups = Binary::uint32($table, 12);

        for ($i = 0; $i < $groups; $i++) {
            $group = 16 + $i * 12;
            $first = Binary::uint32($table, $group);
            if ($code >= $first && $code <= Binary::uint32($table, $group + 4)) {
                return Binary::uint32($table, $group + 8) + ($code - $first);
            }
        }

        return 0;
    }

    /**
     * Picks the Unicode cmap subtable to read code points from.
     *
     * @param string $cmap
     * @return array{table: string, format: int}|null
     */
    private static function unicodeSubtable(string $cmap): ?array
    {
        $chosen = null;
        $count = Binary::uint16($cmap, 2);

        for ($i = 0; $i < $count; $i++) {
            $record = 4 + $i * 8;
            $platform = Binary::uint16($cmap, $record);
            $encoding = Binary::uint16($cmap, $record + 2);
            $isUnicode = $platform === 0 || ($platform === 3 && ($encoding === 1 || $encoding === 10));
            if (!$isUnicode) {
                continue;
            }

            $table = Binary::sub($cmap, Binary::uint32($cmap, $record + 4));
            $format = Binary::uint16($table, 0);
            // Format 12 covers the whole range, so it wins when both are present.
            if ($format === 12) {
                $chosen = ['table' => $table, 'format' => $format];
            } elseif ($format === 4 && $chosen === null) {
                $chosen = ['table' => $table, 'format' => $format];
            }
        }

        return $chosen;
    }

    /**
     * Measures the average character width, weighted by how often each
     * character appears in English.
     *
     * @param array<string, string> $tables
     * @return int|null width in font units, null when it cannot be read
     */
    private function averageWidth(array $tables): ?int
    {
        $cmap = $tables['cmap'] ?? null;
        $hmtx = $tables['hmtx'] ?? null;
        $hhea = $tables['hhea'] ?? null;
        if ($cmap === null || $hmtx === null || $hhea === null) {
            return null;
        }

        $subtable = self::unicodeSubtable($cmap);
        if ($subtable === null) {
            return null;
        }

        $numHMetrics = Binary::uint16($hhea, 34);
        $total = 0.0;
        $weightingTotal = 0.0;

        foreach (self::WEIGHTINGS as $weight) {
            $weightingTotal += $weight;
        }

        foreach (self::WEIGHTINGS as $character => $weight) {
            $code = ord((string) $character);
            $glyph = $subtable['format'] === 12
                ? self::lookupFormat12($subtable['table'], $code)
                : self::lookupFormat4($subtable['table'], $code);
            if (!$glyph) {
                return null;
            }

            $at = min($glyph, $numHMetrics - 1) * 4;
            if ($at + 2 > strlen($hmtx)) {
                return null;
            }
            $total += Binary::uint16($hmtx, $at) * $weight;
        }

        return (int) round($total / $weightingTotal);
    }
}
