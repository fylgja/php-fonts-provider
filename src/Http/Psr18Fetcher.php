<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final readonly class Psr18Fetcher implements Fetcher
{
    /**
     * Timeouts and proxies are configured on the client.
     *
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     * @param int $maxBytes - a larger body is refused rather than read into memory
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private int $maxBytes = 20 * 1024 * 1024,
    ) {
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return Response
     * @throws FetchException
     */
    public function fetch(string $url, array $headers = []): Response
    {
        $request = $this->requestFactory->createRequest('GET', $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new FetchException($exception->getMessage(), 0, $exception);
        }

        $stream = $response->getBody();
        $body = '';
        while (!$stream->eof()) {
            $body .= $stream->read(65536);
            if (strlen($body) > $this->maxBytes) {
                throw new FetchException("response larger than {$this->maxBytes} bytes");
            }
        }

        return new Response($response->getStatusCode(), $body);
    }
}
