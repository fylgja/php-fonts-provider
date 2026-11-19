<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider\Provider;

final class DownloadHosts
{
    /**
     * Whether a URL is https and on one of the given hosts, so a response
     * can never point a download at an internal address.
     *
     * @param string $url
     * @param list<string> $hosts
     * @return bool
     */
    public static function allows(string $url, array $hosts): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !isset($parts['user'])
            && !isset($parts['port'])
            && in_array(strtolower($parts['host'] ?? ''), $hosts, true);
    }
}
