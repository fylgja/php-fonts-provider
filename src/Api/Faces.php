<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Api;

use Fylgja\FontsProvider\Config\FontConfig;
use Fylgja\FontsProvider\Config\Weight;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\Format;

/**
 * Normalizing the faces a provider resolved, whether it returned them as a
 * stylesheet or built them from a catalogue.
 */
final class Faces
{
    /**
     * Reads the numeric weights out of a `font-weight` value, a single weight
     * ("400") or a variable range ("400 900").
     *
     * @param string $value
     * @return list<float>
     */
    public static function parseWeights(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_map('floatval', array_filter($parts, 'is_numeric')));
    }

    /**
     * Collapses the faces of a variable font.
     *
     * A variable font is served as one file per subset and style covering the
     * whole weight axis, so a request for several weights returns the same URL
     * over and over. Those faces are merged into one rule spanning the range.
     *
     * @param list<Face> $faces
     * @return list<Face>
     */
    public static function mergeVariableFaces(array $faces): array
    {
        $merged = [];
        $weights = [];

        foreach ($faces as $face) {
            $key = "{$face->url}|{$face->style}";
            $merged[$key] ??= $face;
            $weights[$key] = [...$weights[$key] ?? [], ...self::parseWeights($face->weight)];
        }

        return array_values(array_map(static function (Face $face, array $list): Face {
            $min = $list === [] ? INF : min($list);
            $max = $list === [] ? -INF : max($list);
            $weight = $min === $max ? Format::number($min) : Format::number($min) . ' ' . Format::number($max);

            return $face->with(['weight' => $weight]);
        }, $merged, $weights));
    }

    /**
     * Sorts faces so the stylesheet reads top down: upright before italic,
     * light before bold, subsets in the configured order.
     *
     * @param list<Face> $faces
     * @param list<string> $subsets - subsets in configuration order
     * @return list<Face>
     */
    public static function sortFaces(array $faces, array $subsets = []): array
    {
        $index = static function (?string $subset) use ($subsets): int {
            $found = $subset === null ? false : array_search($subset, $subsets, true);
            return $found === false ? -1 : $found;
        };

        usort($faces, static function (Face $a, Face $b) use ($index): int {
            $italic = ($a->style === 'italic') <=> ($b->style === 'italic');
            if ($italic !== 0) {
                return $italic;
            }

            $weight = (self::parseWeights($a->weight)[0] ?? 0.0) <=> (self::parseWeights($b->weight)[0] ?? 0.0);
            if ($weight !== 0) {
                return $weight;
            }

            return $index($a->subset) <=> $index($b->subset);
        });

        return $faces;
    }

    /**
     * The weight to measure a face at. A variable face is measured at 400,
     * the default instance of most variable fonts, clamped to its range.
     *
     * @param Face $face
     * @return float
     */
    public static function measureWeight(Face $face): float
    {
        $weights = self::parseWeights($face->weight);
        if (count($weights) < 2) {
            return $weights[0] ?? 400.0;
        }

        return min(max(400.0, $weights[0]), $weights[1]);
    }

    /**
     * Whether a face only holds values that are safe to print in a stylesheet
     * and a file name, with a weight or a range of two weights CSS accepts.
     * A face that fails is dropped.
     *
     * @param Face $face
     * @return bool
     */
    public static function isSafe(Face $face): bool
    {
        foreach ([$face->style, $face->weight, $face->stretch, $face->unicodeRange] as $value) {
            if ($value !== null && !preg_match(FontConfig::SAFE_VALUE, $value)) {
                return false;
            }
        }

        $weights = preg_split('/\s+/', trim($face->weight)) ?: [];
        foreach ($weights as $weight) {
            if (!is_numeric($weight) || (float) $weight < Weight::MIN || (float) $weight > Weight::MAX) {
                return false;
            }
        }
        if (count($weights) > 2) {
            return false;
        }

        return $face->subset === null || (bool) preg_match('/^[A-Za-z0-9_\-\[\]]+$/D', $face->subset);
    }
}
