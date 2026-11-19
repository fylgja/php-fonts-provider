<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Http;

final readonly class Response
{
    /**
     * @param int $status
     * @param string $body
     */
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    /**
     * Whether the status is in the 2xx range.
     *
     * @return bool
     */
    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
