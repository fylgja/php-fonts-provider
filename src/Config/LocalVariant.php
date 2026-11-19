<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

/**
 * A face of the local provider, with its files relative to the fonts folder.
 */
final readonly class LocalVariant
{
    /**
     * @param Weight $weight
     * @param string $style
     * @param string|null $stretch
     * @param string|null $unicodeRange
     * @param string $display
     * @param list<string> $src
     */
    public function __construct(
        public Weight $weight,
        public string $style,
        public ?string $stretch,
        public ?string $unicodeRange,
        public string $display,
        public array $src,
    ) {
    }
}
