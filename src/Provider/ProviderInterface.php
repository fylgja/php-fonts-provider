<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Config\Family;
use Fylgja\FontsProvider\Face;

/**
 * A source of fonts.
 *
 * The capability flags are read during validation, so a config asking for
 * something a provider cannot do is refused before any request is made.
 * A provider returns faces and nothing else; downloading, naming, caching and
 * rendering are the same for all of them.
 */
interface ProviderInterface
{
    /**
     * The `provider` value in the config.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Human readable name, used in messages.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Whether resolving needs the network. Only remote faces are downloaded.
     *
     * @return bool
     */
    public function isRemote(): bool;

    /**
     * Whether the provider serves character subsets.
     *
     * @return bool
     */
    public function supportsSubsets(): bool;

    /**
     * Whether the provider serves variable fonts, so a weight range is allowed.
     *
     * @return bool
     */
    public function supportsVariable(): bool;

    /**
     * Extra guidance shown when a capability is missing.
     *
     * @return string|null
     */
    public function note(): ?string;

    /**
     * Resolves a family into faces. Remote faces carry a `url`, local faces `files`.
     *
     * @param Family $font
     * @param ResolveContext $context
     * @return list<Face>
     * @throws \Exception with a message for the user when the family cannot be resolved
     */
    public function resolve(Family $font, ResolveContext $context): array;

    /**
     * Whether a file URL this provider returned may be downloaded.
     *
     * @param string $url
     * @return bool
     */
    public function allowsDownload(string $url): bool;

    /**
     * A woff or ttf of the same face, to measure when the woff2 cannot be read
     * because ext-brotli is missing.
     *
     * @param Family $font
     * @param Face $face
     * @param ResolveContext $context
     * @return string|null
     */
    public function measureUrl(Family $font, Face $face, ResolveContext $context): ?string;
}
