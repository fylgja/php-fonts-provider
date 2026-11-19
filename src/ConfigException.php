<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

final class ConfigException extends \InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct("Invalid \"fonts\" configuration:\n  " . implode("\n  ", $errors));
    }

    /**
     * Every problem found, one message each.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
