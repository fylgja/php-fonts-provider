<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

use Fylgja\FontsProvider\Http\Fetcher;
use Fylgja\FontsProvider\Storage\StorageInterface;

final readonly class ResolveContext
{
    /**
     * @param StorageInterface $storage - the fonts folder
     * @param Fetcher|null $fetcher - null when only local fonts are used
     */
    public function __construct(
        public StorageInterface $storage,
        private ?Fetcher $fetcher = null,
    ) {
    }

    /**
     * @return Fetcher
     * @throws \LogicException when no fetcher was given
     */
    public function fetcher(): Fetcher
    {
        return $this->fetcher ?? throw new \LogicException('A remote font provider needs a Fetcher, pass one to FontsProvider.');
    }
}
