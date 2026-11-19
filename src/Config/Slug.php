<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

use Normalizer;

final class Slug
{
    /**
     * Turns a font family name into a lowercase, dash separated slug.
     *
     * Words are only split on the separators already in the name, so
     * "JetBrains Mono" stays "jetbrains-mono", the way the catalogues spell
     * their ids.
     *
     * @param string $value
     * @return string
     */
    public static function slugify(string $value): string
    {
        $value = Normalizer::normalize($value, Normalizer::FORM_KD) ?: $value;
        $value = (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', $value);
        // Only ASCII survives the next step, so an ASCII lowercase is enough.
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value));

        return trim($value, '-');
    }
}
