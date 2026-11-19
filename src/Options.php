<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

final readonly class Options
{
    /**
     * @param string $urlBase - path or URL of the fonts folder, as the page sees it
     * @param string $cssSelector - selector for families that do not name one
     * @param bool $force - download every file again, even when it is present
     * @param bool $strict - throw a `WarningException` when there are warnings
     * @param int $maxFileSize - a larger font file is refused
     */
    public function __construct(
        public string $urlBase = '/fonts',
        public string $cssSelector = ':root',
        public bool $force = false,
        public bool $strict = false,
        public int $maxFileSize = 10 * 1024 * 1024,
    ) {
    }
}
