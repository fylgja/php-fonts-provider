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
    /** @var \Closure(string): list<string> */
    private \Closure $resolve;

    /**
     * Timeouts and proxies are configured on the client, and so is following
     * redirects, which should be off: a redirect is returned as a failure.
     *
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     * @param int $maxBytes - a larger body is refused rather than read into memory
     * @param bool $allowPrivateAddresses - also request hosts with private, loopback and other non public addresses
     * @param (\Closure(string): list<string>)|null $resolve - the addresses of a host, defaults to the system resolver
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private int $maxBytes = 20 * 1024 * 1024,
        private bool $allowPrivateAddresses = false,
        ?\Closure $resolve = null,
    ) {
        $this->resolve = $resolve ?? static fn (string $host): array => [
            ...(gethostbynamel($host) ?: []),
            ...array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        ];
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return Response
     * @throws FetchException
     */
    public function fetch(string $url, array $headers = []): Response
    {
        if (!$this->allowPrivateAddresses) {
            $this->checkHost((string) parse_url($url, PHP_URL_HOST));
        }

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

    /**
     * Refuses a host that is, or resolves to, an address that is not public.
     * The client resolves the host again when it connects, see the README
     * for pinning the checked addresses.
     *
     * @param string $host
     * @return void
     * @throws FetchException
     */
    private function checkHost(string $host): void
    {
        $literal = trim($host, '[]');

        // An address in any other spelling, such as 0177.0.0.1, is refused
        // since platforms disagree on what it means.
        if (str_starts_with($host, '[') || preg_match('/(^|\.)(\d+|0x[0-9a-f]*)\.?$/iD', $host)) {
            $packed = @inet_pton($literal);
            if ($packed === false || inet_ntop($packed) !== strtolower($literal) || !self::isPublic($literal)) {
                throw new FetchException("{$literal} is not a public address");
            }
            return;
        }

        $addresses = ($this->resolve)($host);
        if ($addresses === []) {
            throw new FetchException("could not resolve {$host}");
        }
        foreach ($addresses as $address) {
            if (!self::isPublic($address)) {
                throw new FetchException("{$host} resolves to {$address}, which is not a public address");
            }
        }
    }

    /**
     * @param string $address
     * @return bool
     */
    private static function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        // The global range still holds multicast and reserved IPv4, and IPv6
        // outside global unicast 2000::/3, such as NAT64 and multicast.
        return str_contains($address, ':')
            ? preg_match('/^[23][0-9a-f]{3}:/i', $address) === 1 && stripos($address, '3fff:') !== 0
            : (int) $address < 224;
    }
}
