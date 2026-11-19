<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Metrics;

/**
 * Big endian reads on a byte string that throw when out of bounds, so a
 * truncated font fails instead of reading zeros.
 */
final class Binary
{
    /**
     * @param string $bytes
     * @param int $offset
     * @return int
     */
    public static function uint8(string $bytes, int $offset): int
    {
        return ord(self::slice($bytes, $offset, 1));
    }

    /**
     * @param string $bytes
     * @param int $offset
     * @return int
     */
    public static function uint16(string $bytes, int $offset): int
    {
        return unpack('n', self::slice($bytes, $offset, 2))[1];
    }

    /**
     * @param string $bytes
     * @param int $offset
     * @return int
     */
    public static function int16(string $bytes, int $offset): int
    {
        $value = self::uint16($bytes, $offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    /**
     * @param string $bytes
     * @param int $offset
     * @return int
     */
    public static function uint32(string $bytes, int $offset): int
    {
        return unpack('N', self::slice($bytes, $offset, 4))[1];
    }

    /**
     * Reads a 4 byte table tag, without its padding spaces.
     *
     * @param string $bytes
     * @param int $offset
     * @return string
     */
    public static function tag(string $bytes, int $offset): string
    {
        return trim(self::slice($bytes, $offset, 4));
    }

    /**
     * Cuts a range out of the bytes, clamped to the end.
     *
     * @param string $bytes
     * @param int $offset
     * @param int|null $length - null reads to the end
     * @return string
     */
    public static function sub(string $bytes, int $offset, ?int $length = null): string
    {
        if ($offset >= strlen($bytes)) {
            return '';
        }

        return $length === null ? substr($bytes, $offset) : substr($bytes, $offset, max(0, $length));
    }

    /**
     * @param string $bytes
     * @param int $offset
     * @param int $length
     * @return string
     * @throws \RangeException when the range is out of bounds
     */
    private static function slice(string $bytes, int $offset, int $length): string
    {
        if ($offset < 0 || $offset + $length > strlen($bytes)) {
            throw new \RangeException('The offset is out of range.');
        }

        return substr($bytes, $offset, $length);
    }
}
