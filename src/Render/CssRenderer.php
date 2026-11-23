<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Render;

use Fylgja\FontsProvider\Config\FontFormat;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\Metrics\FallbackFace;
use Fylgja\FontsProvider\ResolvedFamily;

final class CssRenderer
{
    /**
     * Quotes a font family name for use in CSS.
     *
     * @param string $family
     * @return string
     */
    public static function quoteFamily(string $family): string
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/D', $family) ? $family : "\"{$family}\"";
    }

    /**
     * Renders a single `@font-face` rule.
     *
     * @param Face $face - with `files` as URLs relative to the stylesheet
     * @return string
     */
    public static function renderFontFace(Face $face): string
    {
        $src = implode(",\n         ", array_map(
            static fn (string $file): string => sprintf('url("%s")', $file)
                . (($format = FontFormat::fromFile($file)) !== null ? " format(\"{$format}\")" : ''),
            $face->files,
        ));

        $declarations = array_filter([
            'font-family: ' . self::quoteFamily((string) $face->family),
            "font-style: {$face->style}",
            "font-weight: {$face->weight}",
            ($face->stretch ?? '') !== '' ? "font-stretch: {$face->stretch}" : null,
            ($face->display ?? '') !== '' ? "font-display: {$face->display}" : null,
            "src: {$src}",
            ($face->unicodeRange ?? '') !== '' ? "unicode-range: {$face->unicodeRange}" : null,
        ]);

        return self::rule($declarations);
    }

    /**
     * Renders the face that adjusts a local font to the metrics of the webfont.
     *
     * @param FallbackFace $face
     * @return string
     */
    public static function renderFallbackFace(FallbackFace $face): string
    {
        return self::rule([
            'font-family: ' . self::quoteFamily($face->family),
            'src: ' . implode(', ', array_map(static fn (string $name): string => "local(\"{$name}\")", $face->local)),
            "size-adjust: {$face->sizeAdjust}",
            "ascent-override: {$face->ascentOverride}",
            "descent-override: {$face->descentOverride}",
            "line-gap-override: {$face->lineGapOverride}",
        ]);
    }

    /**
     * Renders every `@font-face` rule, the faces of each family followed by
     * its fallback face.
     *
     * @param list<ResolvedFamily> $families
     * @param string $urlBase - path or URL from the stylesheet to the fonts folder
     * @return string
     */
    public static function fontFaces(array $families, string $urlBase): string
    {
        $rules = [];
        foreach ($families as $family) {
            foreach ($family->faces as $face) {
                // The configured name and font-display always win, so every
                // provider behaves the same, and the family in the @font-face
                // can never drift from the one the CSS variable refers to.
                $rules[] = self::renderFontFace($face->with([
                    'family' => $family->font->name,
                    'display' => $face->display ?? $family->font->display,
                    'files' => array_map(static fn (string $file): string => "{$urlBase}/{$file}", $face->files),
                ]));
            }

            if ($family->fallbackFace !== null) {
                $rules[] = self::renderFallbackFace($family->fallbackFace);
            }
        }

        return implode("\n\n", $rules);
    }

    /**
     * Renders a block per selector, exposing every family as a CSS variable.
     *
     * @param list<ResolvedFamily> $families
     * @param string $cssSelector - for families that do not name one themselves
     * @return string
     */
    public static function variables(array $families, string $cssSelector = ':root'): string
    {
        $groups = [];
        foreach ($families as $family) {
            $groups[$family->font->cssSelector ?? $cssSelector][] = $family;
        }

        $blocks = [];
        foreach ($groups as $selector => $group) {
            $declarations = array_map(
                static fn (ResolvedFamily $family): string => "    {$family->font->cssVariable}: {$family->fontStack()};",
                $group,
            );

            $blocks[] = "{$selector} {\n" . implode("\n", $declarations) . "\n}";
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param array<int, string> $declarations
     * @return string
     */
    private static function rule(array $declarations): string
    {
        return "@font-face {\n" . implode("\n", array_map(static fn (string $line): string => "    {$line};", $declarations)) . "\n}";
    }
}
