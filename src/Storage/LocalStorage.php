<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Storage;

/**
 * A fonts folder on the local filesystem.
 */
final readonly class LocalStorage implements StorageInterface
{
    private string $root;

    /**
     * @param string $root - absolute path to the fonts folder, created when needed
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function exists(string $path): bool
    {
        $file = $this->resolve($path);

        return $file !== null && is_file($file);
    }

    public function read(string $path): ?string
    {
        $file = $this->resolve($path);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return $contents === false ? null : $contents;
    }

    public function write(string $path, string $contents): void
    {
        $file = $this->resolve($path) ?? throw new \InvalidArgumentException("\"{$path}\" is not inside the fonts folder.");
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Could not create \"{$directory}\".");
        }

        // Written next to the target and renamed, so a reader never sees half a file.
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $contents) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new \RuntimeException("Could not write \"{$file}\".");
        }
    }

    public function location(): string
    {
        return $this->root;
    }

    /**
     * Turns a relative path into an absolute one, refusing anything that
     * would leave the fonts folder.
     *
     * @param string $path
     * @return string|null null when the path is not inside the folder
     */
    private function resolve(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[a-z]:/i', $path)) {
            return $path === '' ? $this->root : null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return $segments === [] ? $this->root : $this->root . '/' . implode('/', $segments);
    }
}
