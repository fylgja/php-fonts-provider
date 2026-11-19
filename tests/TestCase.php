<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\FontsProvider;
use Fylgja\FontsProvider\Metrics\MetricsReader;
use Fylgja\FontsProvider\Storage\LocalStorage;
use Fylgja\FontsProvider\Tests\Support\ReplayFetcher;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected string $fontsDir;
    protected LocalStorage $storage;

    protected function setUp(): void
    {
        $this->fontsDir = sys_get_temp_dir() . '/fylgja-fonts-test-' . bin2hex(random_bytes(6));
        mkdir($this->fontsDir, 0775, true);
        $this->storage = new LocalStorage($this->fontsDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->fontsDir));
    }

    /**
     * A provider on the recorded fixtures, without Brotli unless a reader is given.
     *
     * @param ReplayFetcher $fetcher
     * @param MetricsReader|null $reader
     * @return FontsProvider
     */
    protected function provider(ReplayFetcher $fetcher, ?MetricsReader $reader = null): FontsProvider
    {
        return new FontsProvider($this->storage, $fetcher, null, $reader ?? new MetricsReader(false));
    }

    /**
     * @param string $name
     * @return string
     */
    protected static function fixtureFont(string $name): string
    {
        return (string) file_get_contents(__DIR__ . "/fixtures/fonts/{$name}");
    }
}
