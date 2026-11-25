<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\Config\FontConfig;
use Fylgja\FontsProvider\Http\FetchException;
use Fylgja\FontsProvider\Http\Psr18Fetcher;
use Fylgja\FontsProvider\Format;
use Fylgja\FontsProvider\Provider\Css2Provider;
use Fylgja\FontsProvider\Provider\DownloadHosts;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class SupportingClassesTest extends TestCase
{
    public function testFormatsNumbers(): void
    {
        self::assertSame('100', Format::number(100.0));
        self::assertSame('0', Format::number(-0.0));
        self::assertSame('0.1', Format::number(0.1));
        self::assertSame('92.43%', Format::percent(0.924349));
        self::assertSame('0%', Format::percent(0.0));
    }

    public function testStorageStaysInsideItsFolder(): void
    {
        $this->storage->write('generated/a.woff2', 'x');

        self::assertTrue($this->storage->exists('generated/a.woff2'));
        self::assertTrue($this->storage->exists('./generated//a.woff2'));
        self::assertFalse($this->storage->exists('../' . basename($this->fontsDir) . '/generated/a.woff2'));
        self::assertFalse($this->storage->exists('/etc/hosts'));
        self::assertNull($this->storage->read('generated/../../x'));

        $this->expectException(\InvalidArgumentException::class);
        $this->storage->write('../escape.woff2', 'x');
    }

    public function testCss2RequestEncodesTheFamilyName(): void
    {
        $font = FontConfig::normalize([['name' => 'Open Sans&x=1#', 'provider' => 'google-fonts']])->fonts[0];

        self::assertSame(
            'https://fonts.googleapis.com/css2?family=Open+Sans%26x%3D1%23:wght@400&display=swap',
            Css2Provider::googleFonts()->buildRequestUrl($font),
        );
    }

    public function testDownloadHosts(): void
    {
        self::assertTrue(DownloadHosts::allows('https://cdn.jsdelivr.net/a.woff2', ['cdn.jsdelivr.net']));
        self::assertTrue(DownloadHosts::allows('HTTPS://CDN.JSDELIVR.NET/a.woff2', ['cdn.jsdelivr.net']));
        self::assertFalse(DownloadHosts::allows('http://cdn.jsdelivr.net/a.woff2', ['cdn.jsdelivr.net']));
        self::assertFalse(DownloadHosts::allows('https://cdn.jsdelivr.net:8443/a.woff2', ['cdn.jsdelivr.net']));
        self::assertFalse(DownloadHosts::allows('https://user@cdn.jsdelivr.net/a.woff2', ['cdn.jsdelivr.net']));
        self::assertFalse(DownloadHosts::allows('https://cdn.jsdelivr.net.evil.example/a.woff2', ['cdn.jsdelivr.net']));
        self::assertFalse(DownloadHosts::allows('file:///etc/passwd', ['cdn.jsdelivr.net']));
    }

    public function testPsr18Fetcher(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(404, [], 'missing'),
            ...array_fill(0, 3, new ConnectException('Could not resolve host', new Request('GET', 'https://example.com/'))),
            new Response(503),
            new Response(429),
            new Response(200, [], 'ok'),
            new Response(200, [], str_repeat('x', 100)),
        ]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $fetcher = new Psr18Fetcher(new Client(['handler' => $stack]), new HttpFactory(), 50, resolve: static fn (): array => ['8.8.8.8'], retryDelay: 0);

        $response = $fetcher->fetch('https://example.com/a', ['user-agent' => 'test']);
        self::assertSame(404, $response->status);
        self::assertSame('missing', $response->body);
        self::assertSame('test', $history[0]['request']->getHeaderLine('user-agent'));

        try {
            $fetcher->fetch('https://example.com/b');
            self::fail('Expected a FetchException.');
        } catch (FetchException $exception) {
            self::assertSame('Could not resolve host', $exception->getMessage());
        }

        self::assertSame('ok', $fetcher->fetch('https://example.com/c')->body);
        self::assertCount(7, $history);

        $this->expectExceptionMessage('response larger than 50 bytes');
        $fetcher->fetch('https://example.com/d');
    }

    public function testPsr18FetcherRefusesAddressesThatAreNotPublic(): void
    {
        $sent = 0;
        $client = new Client(['handler' => static function () use (&$sent) {
            $sent++;
            return \GuzzleHttp\Promise\Create::promiseFor(new Response(200, [], 'ok'));
        }]);
        $hosts = ['fonts.example' => ['8.8.8.8', '10.0.0.1'], 'gone.example' => []];
        $fetcher = new Psr18Fetcher($client, new HttpFactory(), resolve: static fn (string $host): array => $hosts[$host] ?? ['8.8.8.8']);

        foreach ([
            'https://fonts.example/a' => 'fonts.example resolves to 10.0.0.1, which is not a public address',
            'https://gone.example/a' => 'could not resolve gone.example',
            'https://169.254.169.254/latest' => '169.254.169.254 is not a public address',
            'https://0177.0.0.1/' => '0177.0.0.1 is not a public address',
            'https://2130706433/' => '2130706433 is not a public address',
            'https://224.0.0.1/' => '224.0.0.1 is not a public address',
            'https://[::ffff:127.0.0.1]/' => '::ffff:127.0.0.1 is not a public address',
            'https://[fd00::1]/' => 'fd00::1 is not a public address',
            'https://[64:ff9b::808:808]/' => '64:ff9b::808:808 is not a public address',
        ] as $url => $message) {
            try {
                $fetcher->fetch($url);
                self::fail("Expected {$url} to be refused.");
            } catch (FetchException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame(0, $sent);
        self::assertSame('ok', $fetcher->fetch('https://8.8.8.8/a')->body);
        self::assertSame('ok', (new Psr18Fetcher($client, new HttpFactory(), allowPrivateAddresses: true))->fetch('https://10.0.0.1/a')->body);
    }
}
