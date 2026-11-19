<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

/**
 * A validated font family with every default filled in.
 */
final readonly class Family
{
    /**
     * @param string $provider - provider id
     * @param string $name
     * @param string $cssVariable
     * @param string|null $cssSelector - null uses the default selector
     * @param bool $preload
     * @param bool $adjustFallback
     * @param string $slug - used in file names
     * @param string $id - used by providers that address a family by id
     * @param string $display
     * @param list<string> $fallbacks
     * @param list<string> $styles - empty for the local provider
     * @param list<Weight> $weights - empty for the local provider
     * @param list<string> $subsets - empty when the provider has no subsets
     * @param list<LocalVariant> $variants - only for the local provider
     */
    public function __construct(
        public string $provider,
        public string $name,
        public string $cssVariable,
        public ?string $cssSelector,
        public bool $preload,
        public bool $adjustFallback,
        public string $slug,
        public string $id,
        public string $display,
        public array $fallbacks,
        public array $styles = [],
        public array $weights = [],
        public array $subsets = [],
        public array $variants = [],
    ) {
    }
}
