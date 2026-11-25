<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Config\FontConfig;
use Fylgja\FontsProvider\Config\FontFormat;
use Fylgja\FontsProvider\Config\NormalizedConfig;
use Fylgja\FontsProvider\Http\Fetcher;
use Fylgja\FontsProvider\Http\Psr18Fetcher;
use Fylgja\FontsProvider\Metrics\FallbackBuilder;
use Fylgja\FontsProvider\Metrics\Metrics;
use Fylgja\FontsProvider\Metrics\MetricsReader;
use Fylgja\FontsProvider\Provider\ProviderInterface;
use Fylgja\FontsProvider\Provider\ProviderRegistry;
use Fylgja\FontsProvider\Provider\ResolveContext;
use Fylgja\FontsProvider\Storage\LocalStorage;
use Fylgja\FontsProvider\Storage\StorageInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Resolves, downloads and measures web fonts, and renders their stylesheet.
 */
final readonly class FontsProvider
{
    private ProviderRegistry $providers;
    private MetricsReader $metrics;

    /**
     * @param StorageInterface $storage - the fonts folder
     * @param Fetcher|null $fetcher - needed for every provider but `local`
     * @param ProviderRegistry|null $providers - defaults to the built in providers
     * @param MetricsReader|null $metrics
     */
    public function __construct(
        private StorageInterface $storage,
        private ?Fetcher $fetcher = null,
        ?ProviderRegistry $providers = null,
        ?MetricsReader $metrics = null,
    ) {
        $this->providers = $providers ?? ProviderRegistry::default();
        $this->metrics = $metrics ?? new MetricsReader();
    }

    /**
     * Creates a provider on a PSR-18 client and a fonts folder.
     *
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     * @param StorageInterface|string $storage - a storage, or the absolute path of the fonts folder
     * @return self
     */
    public static function create(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StorageInterface|string $storage,
    ): self {
        return new self(
            is_string($storage) ? new LocalStorage($storage) : $storage,
            new Psr18Fetcher($client, $requestFactory),
        );
    }

    /**
     * @return ProviderRegistry
     */
    public function providers(): ProviderRegistry
    {
        return $this->providers;
    }

    /**
     * Validates the families without any request, to show errors on a form.
     *
     * @param array<mixed> $fonts
     * @return NormalizedConfig
     */
    public function normalize(array $fonts): NormalizedConfig
    {
        return FontConfig::normalize($fonts, $this->providers);
    }

    /**
     * Resolves every family, downloads the files that are missing and
     * measures each family for its fallback face.
     *
     * A family that cannot be resolved is left without faces, with a
     * warning, so the rest still works.
     *
     * @param array<mixed> $fonts
     * @param Options|null $options
     * @return Result
     * @throws ConfigException when the config is invalid, before any request
     * @throws WarningException in strict mode, when there are warnings
     */
    public function generate(array $fonts, ?Options $options = null): Result
    {
        $options ??= new Options();
        $config = $this->normalize($fonts);

        if (!$config->isValid()) {
            throw new ConfigException($config->errors);
        }

        $context = new ResolveContext($this->storage, $this->fetcher);

        // A variable font serves one file for every weight, so the same URL
        // must map to a single local file instead of one copy per face. Two
        // URLs mapping to the same name would overwrite each other instead.
        $filesByUrl = [];
        $urlsByFile = [];
        $collisions = [];
        $downloads = [];
        $families = [];
        $warnings = [];

        foreach ($config->fonts as $font) {
            $provider = $this->provider($font);
            $faces = [];

            try {
                foreach ($provider->resolve($font, $context) as $face) {
                    if (!$provider->isRemote()) {
                        $faces[] = $face;
                        continue;
                    }

                    $url = (string) $face->url;
                    if (!isset($filesByUrl[$url])) {
                        $file = self::downloadFileName($font, $face);
                        if (isset($urlsByFile[$file])) {
                            $collisions[] = "Skipped {$url} for \"{$font->name}\", its file name {$file} is taken by {$urlsByFile[$file]}.";
                            continue;
                        }
                        $filesByUrl[$url] = $file;
                        $urlsByFile[$file] = $url;
                        $downloads[] = ['url' => $url, 'file' => $file, 'provider' => $provider];
                    }
                    $faces[] = $face->with(['files' => [$filesByUrl[$url]]]);
                }
            } catch (\Exception $exception) {
                $warnings[] = "Skipped \"{$font->name}\", it could not be resolved.\n  {$exception->getMessage()}";
                $faces = [];
            }

            $families[] = ['font' => $font, 'faces' => $faces];
        }

        // After the resolve warnings, the order the Node version reports them in.
        $warnings = [...$warnings, ...$collisions];
        [$records, $failed] = $this->download($downloads, $options);

        $resolved = [];
        foreach ($families as ['font' => $font, 'faces' => $faces]) {
            // Faces whose file never arrived are dropped rather than left pointing
            // at a URL that is not there, which would only surface as a 404.
            $missing = [];
            foreach ($faces as $face) {
                foreach ($face->files as $file) {
                    if (isset($failed[$file])) {
                        $missing[$file] = $failed[$file];
                    }
                }
            }

            if ($missing !== []) {
                $faces = array_values(array_filter(
                    $faces,
                    static fn (Face $face): bool => array_intersect($face->files, array_keys($missing)) === [],
                ));
                $warnings[] = sprintf("Could not download %d font file(s) for \"%s\".\n  %s", count($missing), $font->name, implode("\n  ", array_unique($missing)));
            }

            $metrics = null;
            $fallbackFace = null;
            if ($font->adjustFallback && $faces !== [] && FallbackBuilder::pickFallback($font->fallbacks) !== null) {
                $metrics = $this->measure($font, $faces[0], $context, $options, $warnings);
                $fallbackFace = $metrics !== null ? FallbackBuilder::build($font->name, $font->fallbacks, $metrics) : null;
            }

            $resolved[] = new ResolvedFamily($font, $faces, $fallbackFace, $metrics);
        }

        $result = new Result($resolved, $options, $records, $warnings);

        if ($options->strict && $warnings !== []) {
            throw new WarningException($result);
        }

        return $result;
    }

    /**
     * Builds the name a downloaded file is stored under.
     *
     * @param Family $font
     * @param Face $face
     * @return string
     */
    public static function downloadFileName(Family $font, Face $face): string
    {
        $weight = (string) preg_replace('/\s+/', '-', trim($face->weight));
        $parts = array_filter(
            [$font->slug, $face->subset, $weight, $face->style],
            static fn (?string $part): bool => $part !== null && $part !== '',
        );

        // Google serves some families from a URL without an extension, and only
        // woff2 files are ever downloaded.
        $extension = FontFormat::extension((string) $face->url);
        if (!in_array($extension, FontFormat::EXTENSIONS, true)) {
            $extension = 'woff2';
        }

        return implode('-', $parts) . ".{$extension}";
    }

    /**
     * @param Family $font
     * @return ProviderInterface
     */
    private function provider(Family $font): ProviderInterface
    {
        return $this->providers->get($font->provider) ?? throw new \LogicException("Unknown provider \"{$font->provider}\".");
    }

    /**
     * Downloads the files that are not present yet, reporting failures instead
     * of throwing so one unreachable file cannot take the whole stylesheet down.
     *
     * @param list<array{url: string, file: string, provider: ProviderInterface}> $downloads
     * @param Options $options
     * @return array{list<Download>, array<string, string>} the records, and failed files with their reason
     */
    private function download(array $downloads, Options $options): array
    {
        $records = [];
        $failed = [];
        $budget = ['files' => 0, 'bytes' => 0];

        foreach ($downloads as ['url' => $url, 'file' => $file, 'provider' => $provider]) {
            if (!$options->force && $this->storage->exists($file)) {
                $records[] = new Download($url, $file, DownloadStatus::Present);
                continue;
            }

            $error = ++$budget['files'] > $options->maxFiles
                ? "the run reached its limit of {$options->maxFiles} font files"
                : $this->fetchFile($url, $file, $provider, $options, $budget['bytes']);
            if ($error !== null) {
                $failed[$file] = "{$url} ({$error})";
                $records[] = new Download($url, $file, DownloadStatus::Failed, $error);
                continue;
            }

            $records[] = new Download($url, $file, DownloadStatus::Downloaded);
        }

        return [$records, $failed];
    }

    /**
     * Downloads and stores a single file.
     *
     * @param string $url
     * @param string $file
     * @param ProviderInterface $provider
     * @param Options $options
     * @param int $bytes - downloaded so far in this run, counts this file as well
     * @return string|null why it failed, null on success
     */
    private function fetchFile(string $url, string $file, ProviderInterface $provider, Options $options, int &$bytes): ?string
    {
        if (!$provider->allowsDownload($url)) {
            return 'not a host this provider serves fonts from';
        }

        try {
            $response = $this->fetcher?->fetch($url) ?? throw new \LogicException('No Fetcher was given.');

            if (!$response->ok()) {
                return "HTTP {$response->status}";
            }

            if (strlen($response->body) > $options->maxFileSize) {
                return "larger than {$options->maxFileSize} bytes";
            }

            $bytes += strlen($response->body);
            if ($bytes > $options->maxTotalBytes) {
                return "the run reached its limit of {$options->maxTotalBytes} bytes";
            }

            if (FontFormat::extension($file) === 'woff2' && !str_starts_with($response->body, 'wOF2')) {
                return 'not a woff2 file';
            }

            $this->storage->write($file, $response->body);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * Measures the first file of a family. A woff2 that cannot be read
     * without Brotli is measured on a woff or ttf of the same face, when the
     * provider offers one.
     *
     * @param Family $font
     * @param Face $face
     * @param ResolveContext $context
     * @param Options $options
     * @param list<string> $warnings
     * @return Metrics|null
     */
    private function measure(Family $font, Face $face, ResolveContext $context, Options $options, array &$warnings): ?Metrics
    {
        $bytes = isset($face->files[0]) ? $this->storage->read($face->files[0]) : null;
        if ($bytes === null) {
            return null;
        }

        $metrics = $this->metrics->canRead($bytes)
            ? $this->metrics->read($bytes)
            : $this->measureElsewhere($font, $face, $context, $options);
        if ($metrics !== null) {
            return $metrics;
        }

        $warnings[] = "Could not measure \"{$font->name}\" for its fallback face."
            . ($this->metrics->readsWoff2() ? '' : ' Install ext-brotli to read woff2 files.');

        return null;
    }

    /**
     * Measures a woff or ttf of the same face, for a woff2 that cannot be
     * read without Brotli.
     *
     * @param Family $font
     * @param Face $face
     * @param ResolveContext $context
     * @param Options $options
     * @return Metrics|null
     */
    private function measureElsewhere(Family $font, Face $face, ResolveContext $context, Options $options): ?Metrics
    {
        $provider = $this->provider($font);

        try {
            $url = $provider->isRemote() ? $provider->measureUrl($font, $face, $context) : null;
            if ($url === null || !$provider->allowsDownload($url)) {
                return null;
            }

            $response = $context->fetcher()->fetch($url);

            return $response->ok() && strlen($response->body) <= $options->maxFileSize ? $this->metrics->read($response->body) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
