<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Tests;

use Fylgja\FontsProvider\Config\FontConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FontConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unsafeFonts(): iterable
    {
        yield 'quote in name' => [['name' => 'Inter"; } body { color: red'], 'fonts[0].name contains characters that are not allowed in a font family name.'];
        yield 'style tag in name' => [['name' => '</style><script>'], 'fonts[0].name contains characters that are not allowed in a font family name.'];
        yield 'css variable' => [['name' => 'Inter', 'cssVariable' => '--font: red;'], 'fonts[0].cssVariable "--font: red;" is not a valid custom property name.'];
        yield 'selector' => [['name' => 'Inter', 'cssSelector' => ':root{}'], 'fonts[0].cssSelector contains characters that are not allowed in a selector.'];
        yield 'comment in selector' => [['name' => 'Inter', 'cssSelector' => ':root /*'], 'fonts[0].cssSelector contains characters that are not allowed in a selector.'];
        yield 'backslash in selector' => [['name' => 'Inter', 'cssSelector' => '.a\\7b'], 'fonts[0].cssSelector contains characters that are not allowed in a selector.'];
        yield 'open quote in selector' => [['name' => 'Inter', 'cssSelector' => "[data-font='a\"]"], 'fonts[0].cssSelector has a quote that is not closed.'];
        yield 'long selector' => [['name' => 'Inter', 'cssSelector' => '.' . str_repeat('a', 200)], 'fonts[0].cssSelector is longer than 200 characters.'];
        yield 'id' => [['name' => 'Inter', 'id' => '../../etc'], 'fonts[0].id "../../etc" may only contain letters, digits, "-" and "_".'];
        yield 'display' => [['name' => 'Inter', 'display' => 'swap;color:red'], 'fonts[0].display must be one of: auto, block, swap, fallback, optional.'];
        yield 'fallback' => [['name' => 'Inter', 'fallbacks' => ['sans-serif', 'x;}']], 'fonts[0].fallbacks has a value that is not a font family name: x;}.'];
        yield 'weight' => [['name' => 'Inter', 'weights' => ['bold']], 'fonts[0].weights has "bold", which is not a number or a range of numbers.'];
        yield 'three part weight' => [['name' => 'Inter', 'weights' => ['100 200 300']], 'fonts[0].weights has "100 200 300", which is not a number or a range of numbers.'];
        yield 'weight above 1000' => [['name' => 'Inter', 'weights' => [1001]], 'fonts[0].weights has "1001", which is not a weight from 1 to 1000, or a range from low to high.'];
        yield 'weight of 0' => [['name' => 'Inter', 'weights' => ['0 400']], 'fonts[0].weights has "0 400", which is not a weight from 1 to 1000, or a range from low to high.'];
        yield 'descending range' => [['name' => 'Inter', 'weights' => ['900 100']], 'fonts[0].weights has "900 100", which is not a weight from 1 to 1000, or a range from low to high.'];
        yield 'local weight' => [
            ['provider' => 'local', 'name' => 'Brand', 'variants' => [['src' => ['a.woff2'], 'weight' => '100 2000']]],
            'fonts[0].variants[0].weight "100 2000" is not a weight from 1 to 1000, or a range from low to high.',
        ];
        yield 'subset' => [['name' => 'Inter', 'subsets' => ['../x']], 'fonts[0].subsets has "../x", which is not a valid subset name.'];
        yield 'local path' => [
            ['provider' => 'local', 'name' => 'Brand', 'variants' => [['src' => ['../secret.woff2']]]],
            'fonts[0].variants[0].src "../secret.woff2" must be a path inside the fonts folder.',
        ];
        yield 'local stretch' => [
            ['provider' => 'local', 'name' => 'Brand', 'variants' => [['src' => ['a.woff2'], 'stretch' => '100%;}']]],
            'fonts[0].variants[0].stretch contains characters that are not allowed.',
        ];
        yield 'non string option' => [['name' => 'Inter', 'cssVariable' => 5], 'fonts[0].cssVariable must be a string.'];
    }

    /**
     * @param array<string, mixed> $font
     * @param string $error
     */
    #[DataProvider('unsafeFonts')]
    public function testRejectsUnsafeValues(array $font, string $error): void
    {
        self::assertSame([$error], FontConfig::normalize([$font])->errors);
    }

    public function testAcceptsASelectorWithQuotedAttributeValues(): void
    {
        $config = FontConfig::normalize([['name' => 'Inter', 'cssSelector' => '[data-font="it\'s"] .' . str_repeat('a', 180)]]);

        self::assertSame([], $config->errors);
    }

    public function testAcceptsQuotedAndNamedFallbacks(): void
    {
        $config = FontConfig::normalize([['name' => 'Inter', 'fallbacks' => ['"Helvetica Neue"', "'Segoe UI'", 'Arial', 'system-ui']]]);

        self::assertSame([], $config->errors);
    }

    public function testRejectsWrongTypes(): void
    {
        $config = FontConfig::normalize([['name' => 'Inter', 'styles' => 'italic', 'weights' => [400.5], 'preload' => 'yes']]);

        self::assertSame([
            'fonts[0].preload must be true or false.',
            'fonts[0].styles must be a list of strings.',
            'fonts[0].weights must be a number, a range such as "100 900", or a list of those.',
        ], $config->errors);
    }

    public function testFillsInTheDefaults(): void
    {
        $family = FontConfig::normalize([['name' => 'Inter', 'weights' => [700, '400']]])->fonts[0];

        self::assertSame('fontsource', $family->provider);
        self::assertSame('--font-inter', $family->cssVariable);
        self::assertSame(['400', '700'], array_map(static fn ($weight): string => $weight->css, $family->weights));
        self::assertSame(['normal'], $family->styles);
        self::assertSame(['latin'], $family->subsets);
        self::assertSame(['sans-serif'], $family->fallbacks);
    }
}
