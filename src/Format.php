<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

final class Format
{
    /**
     * Prints a number for CSS or a URL, without a trailing ".0" and with at
     * most four decimals.
     *
     * @param int|float $value
     * @return string
     */
    public static function number(int|float $value): string
    {
        $printed = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $printed === '-0' ? '0' : $printed;
    }

    /**
     * Prints a ratio as a percentage with two decimals, such as "92.43%".
     *
     * @param float $ratio
     * @return string
     */
    public static function percent(float $ratio): string
    {
        return self::number(round($ratio * 100, 2)) . '%';
    }
}
