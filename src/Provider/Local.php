<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Face;

/**
 * Fonts already in the fonts folder. There is nothing to request, so each
 * variant lists its own files.
 */
final class Local implements ProviderInterface
{
    public const ID = 'local';

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'Local';
    }

    public function isRemote(): bool
    {
        return false;
    }

    public function supportsSubsets(): bool
    {
        return false;
    }

    public function supportsVariable(): bool
    {
        return true;
    }

    public function note(): ?string
    {
        return null;
    }

    public function resolve(Family $font, ResolveContext $context): array
    {
        $missing = [];
        $faces = [];

        foreach ($font->variants as $variant) {
            foreach ($variant->src as $file) {
                if (!$context->storage->exists($file)) {
                    $missing[] = $file;
                }
            }

            $faces[] = new Face(
                style: $variant->style,
                weight: $variant->weight->css,
                family: $font->name,
                stretch: $variant->stretch,
                display: $variant->display,
                unicodeRange: $variant->unicodeRange,
                files: $variant->src,
            );
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                "Missing font files for \"{$font->name}\" in {$context->storage->location()}:\n  " . implode("\n  ", $missing),
            );
        }

        return $faces;
    }

    public function allowsDownload(string $url): bool
    {
        return false;
    }

    public function measureUrl(Family $font, Face $face, ResolveContext $context): ?string
    {
        return null;
    }
}
