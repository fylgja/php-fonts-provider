<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

/**
 * Every provider, keyed by its id. Adobe Fonts is deliberately absent, since
 * its license does not allow the font files to be self hosted.
 */
final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param list<ProviderInterface> $providers
     * @param string $defaultId - used when a family names no provider
     */
    public function __construct(array $providers, private readonly string $defaultId = Fontsource::ID)
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    /**
     * The built in providers.
     *
     * @return self
     */
    public static function default(): self
    {
        return new self([
            Css2Provider::googleFonts(),
            Css2Provider::bunnyFonts(),
            new Fontshare(),
            new Fontsource(),
            new Local(),
        ]);
    }

    /**
     * Adds or replaces a provider.
     *
     * @param ProviderInterface $provider
     * @return self
     */
    public function add(ProviderInterface $provider): self
    {
        $this->providers[$provider->id()] = $provider;

        return $this;
    }

    /**
     * @param string $id
     * @return ProviderInterface|null
     */
    public function get(string $id): ?ProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @return string
     */
    public function defaultId(): string
    {
        return $this->defaultId;
    }
}
