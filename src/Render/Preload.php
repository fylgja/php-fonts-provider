<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Render;

/**
 * A font file to preload.
 */
final readonly class Preload
{
    /**
     * @param string $family - the family name
     * @param string $file - relative to the fonts folder
     * @param string $url - `file` prefixed with the URL base
     * @param string $type - media type, such as "font/woff2"
     */
    public function __construct(
        public string $family,
        public string $file,
        public string $url,
        public string $type,
    ) {
    }
}
