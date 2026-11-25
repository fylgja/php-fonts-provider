<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\Api\Stylesheet;
use Fylgja\FontsProvider\Face;
use Fylgja\FontsProvider\ResolvedFamily;
use Fylgja\FontsProvider\Tests\Support\ReplayFetcher;

final class ProvidersTest extends TestCase
{
    public function testFontsourceVariableFont(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'Inter', 'weights' => ['100 900'], 'styles' => ['normal', 'italic'], 'subsets' => ['latin', 'latin-ext']],
        ]);

        self::assertSame([], $result->warnings);
        self::assertSame([
            'inter-latin-100-900-normal.woff2',
            'inter-latin-ext-100-900-normal.woff2',
            'inter-latin-100-900-italic.woff2',
            'inter-latin-ext-100-900-italic.woff2',
        ], $result->families[0]->files());
        self::assertStringContainsString("font-weight: 100 900;\n    font-display: swap;\n    src: url(\"/fonts/inter-latin-100-900-normal.woff2\") format(\"woff2\");\n    unicode-range: U+0000-00FF,", $result->css());
        self::assertSame('107.3%', $result->families[0]->fallbackFace?->sizeAdjust);
    }

    public function testBunnyFonts(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'Roboto', 'provider' => 'bunny-fonts', 'weights' => [400, 700], 'styles' => ['normal', 'italic']],
        ]);

        self::assertSame([], $result->warnings);
        self::assertSame([
            'roboto-latin-400-normal.woff2',
            'roboto-latin-700-normal.woff2',
            'roboto-latin-400-italic.woff2',
            'roboto-latin-700-italic.woff2',
        ], $result->families[0]->files());
        self::assertNotNull($result->families[0]->fallbackFace);
    }

    public function testFontshare(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'Satoshi', 'provider' => 'fontshare', 'weights' => [400, 700], 'styles' => ['normal', 'italic']],
            ['name' => 'JetBrains Mono', 'provider' => 'fontshare', 'id' => 'jet-brains-mono', 'fallbacks' => ['monospace']],
        ]);

        self::assertSame([], $result->warnings);
        self::assertSame(
            [
                ['satoshi-400-normal.woff2', 'satoshi-700-normal.woff2', 'satoshi-400-italic.woff2', 'satoshi-700-italic.woff2'],
                ['jetbrains-mono-400-normal.woff2'],
            ],
            array_map(static fn (ResolvedFamily $family): array => $family->files(), $result->families),
        );
    }

    public function testFontshareWithoutTheWeight(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([['name' => 'Satoshi', 'provider' => 'fontshare', 'weights' => [950]]]);

        self::assertStringStartsWith("Skipped \"Satoshi\", it could not be resolved.\n  Fontshare returned no woff2 files", $result->warnings[0]);
        self::assertSame([], $result->families[0]->faces);
    }

    public function testUnknownFamilies(): void
    {
        $result = $this->provider(new ReplayFetcher())->generate([
            ['name' => 'Not A Real Font'],
            ['name' => 'Not A Real Font', 'provider' => 'google-fonts', 'cssVariable' => '--a'],
            ['name' => 'Not A Real Font', 'provider' => 'bunny-fonts', 'cssVariable' => '--b'],
        ]);

        self::assertCount(3, $result->warnings);
        self::assertStringContainsString('Fontsource has no font with the id "not-a-real-font"', $result->warnings[0]);
        self::assertStringContainsString('Google Fonts rejected "Not A Real Font" (HTTP 400): 400: Font family not found', $result->warnings[1]);
        self::assertStringContainsString("Bunny Fonts returned no woff2 files for \"Not A Real Font\": Please specify a valid font on the 'family' parameter.", $result->warnings[2]);
        self::assertSame('', $result->fontFaceCss());
        self::assertSame(['--font-not-a-real-font', '--a', '--b'], array_keys($result->variables()));
    }

    public function testDropsFacesWithAWeightCssDoesNotAccept(): void
    {
        $css = implode("\n", array_map(
            static fn (string $weight): string => "/* latin */\n@font-face { font-weight: {$weight}; src: url(https://fonts.gstatic.com/a.woff2) format('woff2'); }",
            ['400', '100 900', '0', '1001', '100 200 300', 'bold'],
        ));

        self::assertSame(['400', '100 900'], array_map(static fn (Face $face): string => $face->weight, Stylesheet::parseFontFaces($css)));
    }
}
