<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

/**
 * A single `@font-face`, as a provider resolved it.
 *
 * `files` are paths relative to the fonts folder. `sources` lists every file
 * the provider offered for this face, so a woff or ttf can be measured when
 * a woff2 cannot.
 */
final readonly class Face
{
    /**
     * @param string $style
     * @param string $weight - a single weight or a "min max" range
     * @param string|null $family
     * @param string|null $stretch
     * @param string|null $display
     * @param string|null $subset
     * @param string|null $unicodeRange
     * @param string|null $url - remote file, absent for a local face
     * @param list<string> $files
     * @param list<array{url: string, format: string|null}> $sources
     */
    public function __construct(
        public string $style,
        public string $weight,
        public ?string $family = null,
        public ?string $stretch = null,
        public ?string $display = null,
        public ?string $subset = null,
        public ?string $unicodeRange = null,
        public ?string $url = null,
        public array $files = [],
        public array $sources = [],
    ) {
    }

    /**
     * Returns a copy with some properties changed.
     *
     * @param array<string, mixed> $changes - property names and their new values
     * @return self
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
