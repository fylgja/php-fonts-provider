<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

/**
 * Thrown in strict mode when a run had warnings. The files are already
 * written, the result is attached.
 */
final class WarningException extends \RuntimeException
{
    /**
     * @param Result $result
     */
    public function __construct(private readonly Result $result)
    {
        parent::__construct(sprintf(
            "The fonts were generated with %d warning(s).\n  %s",
            count($result->warnings),
            implode("\n  ", $result->warnings),
        ));
    }

    /**
     * @return Result
     */
    public function result(): Result
    {
        return $this->result;
    }
}
