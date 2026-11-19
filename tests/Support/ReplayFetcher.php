<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests\Support;

use Fylgja\FontsProvider\Http\FetchException;
use Fylgja\FontsProvider\Http\Fetcher;
use Fylgja\FontsProvider\Http\Psr18Fetcher;
use Fylgja\FontsProvider\Http\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Answers from the recorded fixtures, so tests never touch the network.
 */
final class ReplayFetcher implements Fetcher
{
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param array<string, array{status?: int, body?: string}> $overrides - responses by URL, before the fixtures
     * @param bool $offline - throw on every request, like an unreachable network
     */
    public function __construct(
        public array $overrides = [],
        public bool $offline = false,
    ) {
    }

    public function fetch(string $url, array $headers = []): Response
    {
        $this->requests[] = $url;

        if ($this->offline) {
            throw new FetchException('offline');
        }

        if (isset($this->overrides[$url])) {
            return new Response($this->overrides[$url]['status'] ?? 200, $this->overrides[$url]['body'] ?? '');
        }

        $fixture = Fixtures::load(Fixtures::key($url, $headers));
        if ($fixture === null && getenv('FONTS_RECORD')) {
            return (new RecordingFetcher(new Psr18Fetcher(new Client(), new HttpFactory())))->fetch($url, $headers);
        }

        $fixture ?? throw new FetchException("No fixture for {$url}, run the tests with FONTS_RECORD=1 to record it.");

        return new Response($fixture['status'], $fixture['body']);
    }
}
