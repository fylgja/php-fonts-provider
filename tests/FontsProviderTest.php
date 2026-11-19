<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\ConfigException;
use Fylgja\FontsProvider\DownloadStatus;
use Fylgja\FontsProvider\Metrics\MetricsReader;
use Fylgja\FontsProvider\Options;
use Fylgja\FontsProvider\Tests\Support\ReplayFetcher;
use Fylgja\FontsProvider\WarningException;

final class FontsProviderTest extends TestCase
{
    private const PACIFICO = [['name' => 'pacifico', 'cssVariable' => '--font-heading', 'cssSelector' => '.page-title', 'weights' => '400']];

    private const PACIFICO_FALLBACK = <<<'CSS'
        @font-face {
            font-family: "pacifico fallback";
            src: local("Arial"), local("Liberation Sans"), local("Arimo");
            size-adjust: 92.43%;
            ascent-override: 140.97%;
            descent-override: 49.01%;
            line-gap-override: 0%;
        }
        CSS;

    public function testMeasuresWoff2ThroughTheWoffWithoutBrotli(): void
    {
        $fetcher = new ReplayFetcher();
        $result = $this->provider($fetcher)->generate(self::PACIFICO);

        self::assertSame([], $result->warnings);
        self::assertStringContainsString(self::PACIFICO_FALLBACK, $result->css());
        self::assertContains('https://cdn.jsdelivr.net/fontsource/fonts/pacifico@latest/latin-400-normal.woff', $fetcher->requests);
    }

    public function testReadsWoff2WithBrotli(): void
    {
        if (!function_exists('brotli_uncompress')) {
            self::markTestSkipped('Needs ext-brotli.');
        }

        $fetcher = new ReplayFetcher();
        $result = $this->provider($fetcher, new MetricsReader())->generate(self::PACIFICO);

        self::assertSame([], array_filter($fetcher->requests, static fn (string $url): bool => str_ends_with($url, '.woff')));
        self::assertStringContainsString(self::PACIFICO_FALLBACK, $result->css());
    }

    public function testDoesNotDownloadAFileThatIsPresent(): void
    {
        $this->provider(new ReplayFetcher())->generate(self::PACIFICO);

        $fetcher = new ReplayFetcher();
        $result = $this->provider($fetcher)->generate(self::PACIFICO);

        self::assertSame(DownloadStatus::Present, $result->downloads[0]->status);
        self::assertSame(0, $result->downloaded());
        self::assertNotContains('https://cdn.jsdelivr.net/fontsource/fonts/pacifico@latest/latin-400-normal.woff2', $fetcher->requests);
    }

    public function testGoogleFontsAreMeasuredOnTheTtfOfAPlainRequest(): void
    {
        $fetcher = new ReplayFetcher();
        $result = $this->provider($fetcher)->generate([
            ['name' => 'Inter', 'provider' => 'google-fonts', 'weights' => ['100 900'], 'styles' => ['normal', 'italic'], 'subsets' => ['latin', 'latin-ext']],
        ]);

        self::assertSame([], $result->warnings);
        self::assertNotNull($result->families[0]->fallbackFace);
        self::assertNotEmpty(array_filter($fetcher->requests, static fn (string $url): bool => str_ends_with($url, '.ttf')));
    }

    public function testWarnsWhenNothingCanBeMeasured(): void
    {
        $fetcher = new ReplayFetcher([
            'https://cdn.jsdelivr.net/fontsource/fonts/pacifico@latest/latin-400-normal.woff' => ['status' => 404],
        ]);
        $result = $this->provider($fetcher)->generate(self::PACIFICO);

        self::assertSame(['Could not measure "pacifico" for its fallback face. Install ext-brotli to read woff2 files.'], $result->warnings);
        self::assertStringNotContainsString('pacifico fallback', $result->css());
    }

    public function testNamesAFileWithoutAnExtensionAsWoff2(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'Source Code Pro', 'provider' => 'google-fonts', 'weights' => [400, 600]],
        ]);

        self::assertSame([], $result->warnings);
        self::assertSame(['source-code-pro-latin-400-600-normal.woff2'], $result->families[0]->files());
        self::assertStringContainsString('format("woff2")', $result->css());
    }

    public function testRefusesADownloadFromAnotherHost(): void
    {
        $css = "/* latin */\n@font-face { font-family: 'Inter'; src: url(https://evil.example/inter.woff2) format('woff2'); }";
        $result = $this->provider(new ReplayFetcher([
            'https://fonts.googleapis.com/css2?family=Inter:wght@400&display=swap' => ['body' => $css],
        ]))->generate([['name' => 'Inter', 'provider' => 'google-fonts']]);

        self::assertSame(DownloadStatus::Failed, $result->downloads[0]->status);
        self::assertSame('not a host this provider serves fonts from', $result->downloads[0]->error);
        self::assertSame([], $result->families[0]->faces);
    }

    public function testRefusesABodyThatIsNotAWoff2(): void
    {
        $result = $this->provider(new ReplayFetcher([
            'https://cdn.jsdelivr.net/fontsource/fonts/pacifico@latest/latin-400-normal.woff2' => ['body' => '<html>'],
        ]))->generate(self::PACIFICO);

        self::assertSame('not a woff2 file', $result->downloads[0]->error);
        self::assertFalse($this->storage->exists('pacifico-latin-400-normal.woff2'));
    }

    public function testRefusesAFileOverTheSizeLimit(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate(self::PACIFICO, new Options(maxFileSize: 1000));

        self::assertSame('larger than 1000 bytes', $result->downloads[0]->error);
    }

    public function testStrictModeThrowsWithTheResultAttached(): void
    {
        try {
            $this->provider(new ReplayFetcher(offline: true))->generate(self::PACIFICO, new Options(strict: true));
            self::fail('Expected a WarningException.');
        } catch (WarningException $exception) {
            self::assertCount(1, $exception->result()->warnings);
            self::assertStringContainsString('--font-heading: pacifico, sans-serif;', $exception->result()->css());
        }
    }

    public function testInvalidConfigThrowsBeforeAnyRequest(): void
    {
        $fetcher = new ReplayFetcher();

        try {
            $this->provider($fetcher)->generate([['name' => 'Inter', 'styles' => ['bold']], ['name' => '']]);
            self::fail('Expected a ConfigException.');
        } catch (ConfigException $exception) {
            self::assertSame([
                'fonts[0].styles only supports "normal" and "italic", received: bold.',
                'fonts[1].name is required.',
            ], $exception->errors());
        }

        self::assertSame([], $fetcher->requests);
    }

    public function testSplitsTheStylesheetForInlineUse(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate(self::PACIFICO, new Options(urlBase: 'https://example.com/media/fonts'));

        self::assertStringStartsWith('@font-face {', $result->css());
        self::assertStringContainsString('url("https://example.com/media/fonts/pacifico-latin-400-normal.woff2")', $result->fontFaceCss());
        self::assertStringNotContainsString('--font-', $result->fontFaceCss());
        self::assertSame(".page-title {\n    --font-heading: pacifico, \"pacifico fallback\", sans-serif;\n}", $result->variablesCss());
    }

    public function testVariablesForCssThatDeclaresThemItself(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'pacifico', 'cssVariable' => '--font-heading'],
            ['name' => 'Roboto', 'weights' => [400, 700], 'cssVariable' => 'font-body', 'fallbacks' => ['cursive']],
        ]);

        self::assertSame([], $result->warnings);
        self::assertSame([
            '--font-heading' => 'pacifico, "pacifico fallback", sans-serif',
            '--font-body' => 'Roboto, cursive',
        ], $result->variables());
        self::assertSame(
            ['pacifico-latin-400-normal.woff2', 'roboto-latin-400-normal.woff2', 'roboto-latin-700-normal.woff2'],
            array_values(array_diff(scandir($this->fontsDir), ['.', '..'])),
        );
    }

    public function testPreloads(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([self::PACIFICO[0] + ['preload' => true]], new Options(urlBase: '/media/fonts'));

        self::assertSame(
            '<link rel="preload" as="font" type="font/woff2" href="/media/fonts/pacifico-latin-400-normal.woff2" crossorigin>',
            $result->preloadHtml(),
        );
    }

    public function testNoFonts(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([]);

        self::assertSame('', $result->css());
    }

    public function testLocalFontsNeedNoFetcher(): void
    {
        $this->storage->write('brand/brand.ttf', self::fixtureFont('substitute.ttf'));
        $provider = new \Fylgja\FontsProvider\FontsProvider($this->storage);

        $result = $provider->generate([['provider' => 'local', 'name' => 'Brand', 'variants' => [['src' => ['brand/brand.ttf']]]]]);

        self::assertSame([], $result->warnings);
        self::assertStringContainsString('url("/fonts/brand/brand.ttf") format("truetype")', $result->css());
        self::assertStringContainsString('size-adjust: 92.43%;', $result->css());
    }
}
