<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Storage;

/**
 * The fonts folder. Every path is relative to it and uses "/", such as
 * "inter-latin-400-normal.woff2".
 */
interface StorageInterface
{
    /**
     * @param string $path
     * @return bool false as well for a path outside the folder
     */
    public function exists(string $path): bool;

    /**
     * @param string $path
     * @return string|null null when the file does not exist
     */
    public function read(string $path): ?string;

    /**
     * Writes a file, creating its folder when needed.
     *
     * @param string $path
     * @param string $contents
     * @return void
     */
    public function write(string $path, string $contents): void;

    /**
     * Describes the folder in messages, such as its absolute path.
     *
     * @return string
     */
    public function location(): string;
}
