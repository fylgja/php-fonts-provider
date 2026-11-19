<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests\Support;

use Fylgja\FontsProvider\Api\Stylesheet;

/**
 * Recorded HTTP responses, keyed by the SHA-1 of the URL.
 */
final class Fixtures
{
    public const DIR = __DIR__ . '/../fixtures/http';

    /**
     * The fixture name of a request. A request made with the plain user agent
     * gets a different answer from Google, so it is stored separately.
     *
     * @param string $url
     * @param array<string, string> $headers
     * @return string
     */
    public static function key(string $url, array $headers = []): string
    {
        $plain = ($headers['user-agent'] ?? null) === Stylesheet::PLAIN_USER_AGENT;

        return sha1($plain ? "{$url}\nplain" : $url);
    }

    /**
     * @param string $key
     * @return array{url: string, status: int, body: string}|null
     */
    public static function load(string $key): ?array
    {
        $file = self::DIR . "/{$key}.json";
        if (!is_file($file)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $body = isset($meta['bodyFile'])
            ? (string) file_get_contents(self::DIR . '/' . $meta['bodyFile'])
            : (string) ($meta['body'] ?? '');

        return ['url' => $meta['url'], 'status' => $meta['status'], 'body' => $body];
    }

    /**
     * @param string $key
     * @param string $url
     * @param int $status
     * @param string $body
     * @return void
     */
    public static function save(string $key, string $url, int $status, string $body): void
    {
        $meta = ['url' => $url, 'status' => $status];

        if (preg_match('//u', $body) && !str_contains($body, "\0")) {
            $meta['body'] = $body;
        } else {
            $meta['bodyFile'] = "{$key}.bin";
            file_put_contents(self::DIR . "/{$key}.bin", $body);
        }

        file_put_contents(
            self::DIR . "/{$key}.json",
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );
    }
}
