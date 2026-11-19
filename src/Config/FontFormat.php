<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

final class FontFormat
{
    public const EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf', 'eot', 'svg'];

    private const FORMATS = [
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'otf' => 'opentype',
        'eot' => 'embedded-opentype',
        'svg' => 'svg',
    ];

    private const MIME_TYPES = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'svg' => 'image/svg+xml',
    ];

    /**
     * Reads the lowercase extension of a file name, path or URL.
     *
     * @param string $file
     * @return string
     */
    public static function extension(string $file): string
    {
        $path = explode('?', $file, 2)[0];
        $parts = explode('.', $path);

        return strtolower(end($parts));
    }

    /**
     * Looks up the CSS `format()` hint for a font file.
     *
     * @param string $file - file name, path or URL
     * @return string|null null when the extension is unknown
     */
    public static function fromFile(string $file): ?string
    {
        return self::FORMATS[self::extension($file)] ?? null;
    }

    /**
     * Looks up the media type for a font file, as used in a preload link.
     *
     * @param string $file
     * @return string|null null when the extension is unknown
     */
    public static function mimeType(string $file): ?string
    {
        return self::MIME_TYPES[self::extension($file)] ?? null;
    }
}
