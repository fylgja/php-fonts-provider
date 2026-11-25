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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\RequestInterface;

/**
 * Answers from the recorded fixtures, so tests never touch the network. It
 * runs through `Psr18Fetcher`, so the address check is tested
 * too, on a fake DNS where every host is public unless listed.
 */
final class ReplayFetcher implements Fetcher
{
    /** @var list<string> every request sent */
    public array $requests = [];

    private Psr18Fetcher $fetcher;

    /** The URL as given, which Guzzle would encode. */
    private string $url = '';

    /**
     * @param array<string, array{status?: int, headers?: array<string, string>, body?: string}> $overrides - responses by URL, before the fixtures
     * @param bool $offline - throw on every request, like an unreachable network
     * @param array<string, list<string>> $hosts - addresses by host for the fake DNS
     * @param bool $allowPrivateAddresses
     */
    public function __construct(
        public array $overrides = [],
        public bool $offline = false,
        array $hosts = [],
        bool $allowPrivateAddresses = false,
    ) {
        $this->fetcher = new Psr18Fetcher(
            new Client(['handler' => $this->handle(...)]),
            new HttpFactory(),
            allowPrivateAddresses: $allowPrivateAddresses,
            resolve: static fn (string $host): array => $hosts[$host] ?? ['8.8.8.8'],
        );
    }

    public function fetch(string $url, array $headers = []): Response
    {
        $this->url = $url;

        return $this->fetcher->fetch($url, $headers);
    }

    /**
     * The Guzzle handler, answering a request from the overrides or fixtures.
     *
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    private function handle(RequestInterface $request): PromiseInterface
    {
        $url = $this->url;
        $this->requests[] = $url;

        if ($this->offline) {
            return Create::rejectionFor(new ConnectException('offline', $request));
        }

        if (isset($this->overrides[$url])) {
            $override = $this->overrides[$url];
            return Create::promiseFor(new Psr7Response($override['status'] ?? 200, $override['headers'] ?? [], $override['body'] ?? ''));
        }

        $headers = ['user-agent' => $request->getHeaderLine('user-agent')];
        $fixture = Fixtures::load(Fixtures::key($url, $headers));
        if ($fixture === null && getenv('FONTS_RECORD')) {
            $recorded = (new RecordingFetcher(new Psr18Fetcher(new Client(), new HttpFactory())))->fetch($url, $headers);
            $fixture = ['status' => $recorded->status, 'body' => $recorded->body];
        }

        return $fixture === null
            ? Create::rejectionFor(new FetchException("No fixture for {$url}, run the tests with FONTS_RECORD=1 to record it."))
            : Create::promiseFor(new Psr7Response($fixture['status'], [], $fixture['body']));
    }
}
