<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Http;

/**
 * The one HTTP call this library makes, a GET returning the whole body.
 * `Psr18Fetcher` implements it on any PSR-18 client.
 */
interface Fetcher
{
    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return Response any status, only a failed connection throws
     * @throws FetchException when no response was received
     */
    public function fetch(string $url, array $headers = []): Response;
}
