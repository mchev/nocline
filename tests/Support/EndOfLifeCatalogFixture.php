<?php

namespace Tests\Support;

use App\Modules\Hosts\Services\EndOfLifeCatalog;
use Illuminate\Support\Facades\Cache;

final class EndOfLifeCatalogFixture
{
    public static function seedMinimalCatalog(): void
    {
        Cache::put(EndOfLifeCatalog::CACHE_KEY, [
            'fetched_at' => now()->toIso8601String(),
            'products' => [
                'php' => [
                    'releases' => [
                        [
                            'name' => '8.1',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2025-11-25',
                            'latest' => '8.1.33',
                        ],
                        [
                            'name' => '8.5',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => '2029-12-31',
                            'latest' => '8.5.13',
                        ],
                    ],
                ],
                'nodejs' => [
                    'releases' => [
                        [
                            'name' => '18',
                            'isEol' => true,
                            'isLts' => true,
                            'isMaintained' => false,
                            'eolFrom' => '2025-04-30',
                            'latest' => '18.20.8',
                        ],
                        [
                            'name' => '22',
                            'isEol' => false,
                            'isLts' => true,
                            'isMaintained' => true,
                            'eolFrom' => '2027-04-30',
                            'latest' => '22.14.0',
                        ],
                    ],
                ],
                'postgresql' => [
                    'releases' => [
                        [
                            'name' => '14',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2026-11-12',
                            'latest' => '14.20',
                        ],
                    ],
                ],
                'redis' => [
                    'releases' => [
                        [
                            'name' => '7.0',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2024-07-29',
                            'latest' => '7.0.15',
                        ],
                    ],
                ],
                'nginx' => [
                    'releases' => [
                        [
                            'name' => '1.31',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => null,
                            'latest' => '1.31.9',
                        ],
                    ],
                ],
            ],
        ], now()->addDay());
    }
}
