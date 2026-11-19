<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Metrics\FallbackFace;
use Fylgja\FontsProvider\Metrics\Metrics;
use Fylgja\FontsProvider\Render\CssRenderer;

/**
 * A family with the faces it resolved to. A family without faces could not be
 * resolved, its CSS variable is still declared so the stylesheet stays valid.
 */
final readonly class ResolvedFamily
{
    /**
     * @param Family $font
     * @param list<Face> $faces - with `files` relative to the fonts folder
     * @param FallbackFace|null $fallbackFace
     * @param Metrics|null $metrics - of the first file, when measured
     */
    public function __construct(
        public Family $font,
        public array $faces,
        public ?FallbackFace $fallbackFace = null,
        public ?Metrics $metrics = null,
    ) {
    }

    /**
     * The value of the family's CSS variable: the family, its fallback face
     * when there is one, and the configured fallbacks.
     *
     * @return string
     */
    public function fontStack(): string
    {
        return implode(', ', [
            CssRenderer::quoteFamily($this->font->name),
            ...($this->fallbackFace !== null ? [CssRenderer::quoteFamily($this->fallbackFace->family)] : []),
            ...$this->font->fallbacks,
        ]);
    }

    /**
     * Every file of every face, without duplicates.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = [];
        foreach ($this->faces as $face) {
            foreach ($face->files as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }
}
