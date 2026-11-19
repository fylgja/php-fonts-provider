<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

/**
 * A font file this run needed.
 */
final readonly class Download
{
    /**
     * @param string $url
     * @param string $file - relative to the fonts folder
     * @param DownloadStatus $status
     * @param string|null $error - why it failed
     */
    public function __construct(
        public string $url,
        public string $file,
        public DownloadStatus $status,
        public ?string $error = null,
    ) {
    }
}
