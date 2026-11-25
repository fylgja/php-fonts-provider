<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

/**
 * A weight in the three spellings it is needed in: the CSS2 API ("300..700"),
 * the generated CSS ("300 700") and file names ("300-700").
 */
final readonly class Weight
{
    /** The lowest and highest weight CSS accepts. */
    public const MIN = 1;
    public const MAX = 1000;

    /**
     * @param string $api
     * @param string $css
     * @param string $slug
     */
    public function __construct(
        public string $api,
        public string $css,
        public string $slug,
    ) {
    }

    /**
     * Normalizes a weight. A range like "300 700", "300..700" or "300-700"
     * describes a variable font axis. A value with more than two parts is kept
     * as is, so validation refuses it instead of dropping a part.
     *
     * @param int|string $weight
     * @return self
     */
    public static function normalize(int|string $weight): self
    {
        $value = trim((string) $weight);
        $range = preg_split('/\s*(?:\.\.|\s|-)\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($range) === 2) {
            return new self("{$range[0]}..{$range[1]}", "{$range[0]} {$range[1]}", "{$range[0]}-{$range[1]}");
        }

        return new self($value, $value, $value);
    }

    /**
     * Whether this weight lies within the weights CSS accepts, with the lowest
     * weight of a range first.
     *
     * @return bool
     */
    public function isInBounds(): bool
    {
        [$min, $max] = $this->bounds();

        return $min >= self::MIN && $max <= self::MAX && ($min < $max || !$this->isRange());
    }

    /**
     * Whether this weight is a variable range.
     *
     * @return bool
     */
    public function isRange(): bool
    {
        return str_contains($this->css, ' ');
    }

    /**
     * The lowest and highest weight this covers.
     *
     * @return array{float, float}
     */
    public function bounds(): array
    {
        $parts = array_map('floatval', explode('..', $this->api));

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    /**
     * Sorts weights numerically and drops exact duplicates, since the CSS2 API
     * requires axis values in ascending order and rejects a repeated one.
     *
     * @param list<self> $weights
     * @return list<self>
     */
    public static function sort(array $weights): array
    {
        $unique = [];
        foreach ($weights as $weight) {
            $unique[$weight->api] = $weight;
        }
        $unique = array_values($unique);

        usort($unique, static fn (self $a, self $b): int => $a->bounds() <=> $b->bounds());

        return $unique;
    }

    /**
     * Finds a weight that falls inside the weight before it, such as "400"
     * next to "300 700", which would produce two competing `@font-face` rules.
     *
     * @param list<self> $weights - sorted, unique weights
     * @return array{self, self}|null the overlapping pair
     */
    public static function findOverlap(array $weights): ?array
    {
        for ($i = 1; $i < count($weights); $i++) {
            if ($weights[$i]->bounds()[0] <= $weights[$i - 1]->bounds()[1]) {
                return [$weights[$i - 1], $weights[$i]];
            }
        }

        return null;
    }
}
