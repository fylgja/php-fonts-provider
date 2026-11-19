<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Config;

final readonly class NormalizedConfig
{
    /**
     * @param list<Family> $fonts - the valid families
     * @param list<string> $errors - one message per problem found
     */
    public function __construct(
        public array $fonts,
        public array $errors,
    ) {
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
