<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests\Support;

use Fylgja\FontsProvider\Http\Fetcher;
use Fylgja\FontsProvider\Http\Response;

/**
 * Passes requests on to the network and stores every response as a fixture.
 *
 * Fontshare fonts fall under the ITF Free Font License, which does not allow
 * redistributing them, so an OFL font of the same format is stored instead.
 */
final class RecordingFetcher implements Fetcher
{
    private const SUBSTITUTE_HOSTS = ['cdn.fontshare.com'];

    /**
     * @param Fetcher $inner
     */
    public function __construct(private readonly Fetcher $inner)
    {
    }

    public function fetch(string $url, array $headers = []): Response
    {
        $key = Fixtures::key($url, $headers);
        $existing = Fixtures::load($key);
        if ($existing !== null) {
            return new Response($existing['status'], $existing['body']);
        }

        $response = $this->inner->fetch($url, $headers);
        $body = $response->body;

        if ($response->ok() && in_array(parse_url($url, PHP_URL_HOST), self::SUBSTITUTE_HOSTS, true)) {
            $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $substitute = __DIR__ . "/../fixtures/fonts/substitute.{$extension}";
            if (is_file($substitute)) {
                $body = (string) file_get_contents($substitute);
            }
        }

        Fixtures::save($key, $url, $response->status, $body);

        return new Response($response->status, $body);
    }
}
