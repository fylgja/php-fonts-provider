<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

// Runs tests/parity/cases.json through this library and prints the report
// run.mjs prints, on the recorded HTTP responses of this repo.

declare(strict_types=1);

use Fylgja\FontsProvider\Config\FontConfig;
use Fylgja\FontsProvider\Download;
use Fylgja\FontsProvider\FontsProvider;
use Fylgja\FontsProvider\Metrics\MetricsReader;
use Fylgja\FontsProvider\Options;
use Fylgja\FontsProvider\Storage\LocalStorage;
use Fylgja\FontsProvider\Tests\Support\ReplayFetcher;

require __DIR__ . '/../../vendor/autoload.php';

const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

$fixtures = dirname(__DIR__) . '/fixtures';
$cases = json_decode((string) file_get_contents(__DIR__ . '/cases.json'), true, 512, JSON_THROW_ON_ERROR);

// Node always reads woff2, so without ext-brotli it decompresses for this side too.
$brotli = function_exists('brotli_uncompress') ? null : static function (string $data): string|false {
    $process = proc_open(
        ['node', '-e', 'process.stdout.write(require("zlib").brotliDecompressSync(require("fs").readFileSync(0)))'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    if (!is_resource($process)) {
        return false;
    }
    fwrite($pipes[0], $data);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 ? (string) $output : false;
};

/**
 * @param string $line
 * @return void
 */
function out(string $line): void
{
    echo $line, "\n";
}

/**
 * Lists the files in a folder and its subfolders.
 *
 * @param string $dir
 * @param string $prefix
 * @return list<string>
 */
function list_files(string $dir, string $prefix = ''): array
{
    $files = [];
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
        $files = is_dir("{$dir}/{$name}")
            ? [...$files, ...list_files("{$dir}/{$name}", "{$prefix}{$name}/")]
            : [...$files, "{$prefix}{$name}"];
    }
    usort($files, static fn (string $a, string $b): int => strcmp($a, $b));

    return $files;
}

/**
 * @param string $dir
 * @return void
 */
function remove_dir(string $dir): void
{
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
        is_dir("{$dir}/{$name}") ? remove_dir("{$dir}/{$name}") : unlink("{$dir}/{$name}");
    }
    rmdir($dir);
}

foreach ($cases['normalize'] as $case) {
    out("### normalize: {$case['name']}");
    $config = is_array($case['fonts']) ? FontConfig::normalize($case['fonts']) : null;
    foreach ($config->fonts as $font) {
        out(json_encode($font, FLAGS));
    }
    foreach ($config->errors as $error) {
        out("ERROR: {$error}");
    }
}

foreach ($cases['generate'] as $case) {
    out("### generate: {$case['name']}");
    $storage = sys_get_temp_dir() . '/fylgja-parity-' . bin2hex(random_bytes(6));
    mkdir($storage, 0775, true);
    foreach ($case['files'] ?? [] as $file => $fixture) {
        @mkdir(dirname("{$storage}/{$file}"), 0775, true);
        copy("{$fixtures}/fonts/{$fixture}", "{$storage}/{$file}");
    }

    $provider = new FontsProvider(
        new LocalStorage($storage),
        new ReplayFetcher(offline: $case['offline'] ?? false),
        null,
        new MetricsReader($brotli),
    );

    try {
        $result = $provider->generate($case['fonts'], new Options(...($case['options'] ?? [])));
        out('--- css');
        out($result->css());
        out('--- warnings');
        foreach ($result->warnings as $warning) {
            out(str_replace($storage, '<fontsDir>', $warning));
        }
        out('--- downloads');
        foreach ($result->downloads as $download) {
            /** @var Download $download */
            out(trim("{$download->status->value} {$download->file} {$download->error}"));
        }
        out('--- files');
        foreach (list_files($storage) as $file) {
            out($file);
        }
        out('--- variables');
        out(json_encode((object) $result->variables(), FLAGS));
        out('--- metrics');
        foreach ($result->families as $family) {
            out(json_encode($family->metrics, FLAGS));
        }
        out('--- preload');
        out($result->preloadHtml());
    } catch (\Exception $exception) {
        out("ERROR: {$exception->getMessage()}");
    }

    remove_dir($storage);
}
