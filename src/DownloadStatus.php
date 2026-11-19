<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\FontsProvider;

enum DownloadStatus: string
{
    case Downloaded = 'downloaded';
    case Present = 'present';
    case Failed = 'failed';
}
