<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

use Fylgja\FontsProvider\Render\CssRenderer;
use Fylgja\FontsProvider\Render\Preload;
use Fylgja\FontsProvider\Render\PreloadRenderer;

final readonly class Result
{
    /**
     * @param list<ResolvedFamily> $families
     * @param Options $options
     * @param list<Download> $downloads
     * @param list<string> $warnings
     */
    public function __construct(
        public array $families,
        public Options $options,
        public array $downloads = [],
        public array $warnings = [],
    ) {
    }

    /**
     * The `@font-face` rules followed by the CSS variables.
     *
     * @return string
     */
    public function css(): string
    {
        return trim($this->fontFaceCss() . "\n\n" . $this->variablesCss());
    }

    /**
     * Only the `@font-face` rules, the faces and the fallback faces.
     *
     * @return string
     */
    public function fontFaceCss(): string
    {
        return CssRenderer::fontFaces($this->families, $this->options->urlBase);
    }

    /**
     * Only the selector blocks declaring the CSS variables.
     *
     * @return string
     */
    public function variablesCss(): string
    {
        return CssRenderer::variables($this->families, $this->options->cssSelector);
    }

    /**
     * The value of each family's CSS variable, for a caller that declares the
     * variables itself, such as a design tokens module.
     *
     * @return array<string, string> font stacks keyed by CSS variable name
     */
    public function variables(): array
    {
        $variables = [];
        foreach ($this->families as $family) {
            $variables[$family->font->cssVariable] = $family->fontStack();
        }

        return $variables;
    }

    /**
     * The files of every family with `preload` set.
     *
     * @return list<Preload>
     */
    public function preloads(): array
    {
        return PreloadRenderer::preloads($this->families, $this->options->urlBase);
    }

    /**
     * Preload links for the head of a page.
     *
     * @return string
     */
    public function preloadHtml(): string
    {
        return PreloadRenderer::html($this->preloads());
    }

    /**
     * The number of files downloaded in this run.
     *
     * @return int
     */
    public function downloaded(): int
    {
        return count(array_filter($this->downloads, static fn (Download $download): bool => $download->status === DownloadStatus::Downloaded));
    }
}
