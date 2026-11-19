<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Render;

use Fylgja\FontsProvider\Config\FontFormat;
use Fylgja\FontsProvider\ResolvedFamily;

final class PreloadRenderer
{
    /**
     * Lists the files of every family that asked to be preloaded.
     *
     * @param list<ResolvedFamily> $families
     * @param string $urlBase - path or URL from the page to the fonts folder
     * @return list<Preload>
     */
    public static function preloads(array $families, string $urlBase): array
    {
        $preloads = [];
        foreach ($families as $family) {
            if (!$family->font->preload) {
                continue;
            }
            foreach ($family->files() as $file) {
                $preloads[] = new Preload(
                    $family->font->name,
                    $file,
                    "{$urlBase}/{$file}",
                    FontFormat::mimeType($file) ?? 'font/woff2',
                );
            }
        }

        return $preloads;
    }

    /**
     * Renders preload links for the head of a page.
     *
     * @param list<Preload> $preloads
     * @return string one link per line
     */
    public static function html(array $preloads): string
    {
        return implode("\n", array_map(static fn (Preload $preload): string => sprintf(
            '<link rel="preload" as="font" type="%s" href="%s" crossorigin>',
            htmlspecialchars($preload->type, ENT_QUOTES),
            htmlspecialchars($preload->url, ENT_QUOTES),
        ), $preloads));
    }
}
