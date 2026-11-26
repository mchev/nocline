<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Hosts\Support\StackVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cached release-cycle data from endoflife.date (refreshed by nocline:stack-eol:refresh).
 *
 * @phpstan-type ReleaseCycle array{
 *     name: string,
 *     isEol: bool,
 *     isLts: bool,
 *     isMaintained: bool,
 *     eolFrom: string|null,
 *     latest: string|null,
 * }
 */
class EndOfLifeCatalog
{
    public const CACHE_KEY = 'nocline:stack-eol-catalog';

    /** @var array<string, string> agent stack key => endoflife.date product slug */
    public const STACK_TO_PRODUCT = [
        'php' => 'php',
        'node' => 'nodejs',
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
        'postgresql' => 'postgresql',
        'redis' => 'redis',
        'nginx' => 'nginx',
        'docker' => 'docker-engine',
    ];

    /**
     * Fetch product cycles and merge them into the cache.
     */
    public function refresh(): bool
    {
        if (! config('nocline.stack_eol_check')) {
            return false;
        }

        $base = rtrim((string) config('nocline.stack_eol_api'), '/');
        $existing = $this->catalog();
        $products = is_array($existing['products'] ?? null) ? $existing['products'] : [];
        $fetched = 0;

        foreach (array_values(array_unique(array_values(self::STACK_TO_PRODUCT))) as $slug) {
            try {
                $response = Http::acceptJson()
                    ->timeout(15)
                    ->withUserAgent('nocline/'.config('nocline.version'))
                    ->get("{$base}/products/{$slug}/");
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $releases = $this->normalizeReleases($response->json('result.releases'));

            if ($releases === null) {
                continue;
            }

            $products[$slug] = [
                'releases' => $releases,
            ];
            $fetched++;
        }

        if ($fetched === 0) {
            return false;
        }

        Cache::put(self::CACHE_KEY, [
            'fetched_at' => now()->toIso8601String(),
            'products' => $products,
        ], now()->addDays(7));

        return true;
    }

    /**
     * @return array{fetched_at: string, products: array<string, array{releases: list<ReleaseCycle>}>}|null
     */
    public function catalog(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! $this->isCatalogPayload($cached)) {
            return null;
        }

        /** @var array{fetched_at: string, products: array<string, array{releases: list<ReleaseCycle>}>} $cached */
        return $cached;
    }

    /**
     * @return list<ReleaseCycle>|null
     */
    public function releasesForStackKey(string $stackKey): ?array
    {
        $slug = self::STACK_TO_PRODUCT[$stackKey] ?? null;

        if ($slug === null) {
            return null;
        }

        $catalog = $this->catalog();

        if ($catalog === null) {
            return null;
        }

        $releases = $catalog['products'][$slug]['releases'] ?? null;

        return is_array($releases) ? $releases : null;
    }

    /**
     * @param  list<ReleaseCycle>|null  $releases
     * @return ReleaseCycle|null
     */
    public function matchRelease(?array $releases, string $version): ?array
    {
        if ($releases === null || $releases === []) {
            return null;
        }

        $parsed = StackVersion::parse($version);

        if ($parsed === null) {
            return null;
        }

        $candidates = [
            $parsed['major'].'.'.$parsed['minor'],
            (string) $parsed['major'],
        ];

        foreach ($candidates as $candidate) {
            foreach ($releases as $release) {
                if ($release['name'] === $candidate) {
                    return $release;
                }
            }
        }

        return null;
    }

    /**
     * @return list<ReleaseCycle>|null
     */
    private function normalizeReleases(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $out = [];

        foreach ($raw as $item) {
            if (! is_array($item) || ! is_string($item['name'] ?? null)) {
                continue;
            }

            $latest = $item['latest'] ?? null;
            $latestName = is_array($latest) && is_string($latest['name'] ?? null) ? $latest['name'] : null;

            $out[] = [
                'name' => $item['name'],
                'isEol' => (bool) ($item['isEol'] ?? false),
                'isLts' => (bool) ($item['isLts'] ?? false),
                'isMaintained' => (bool) ($item['isMaintained'] ?? true),
                'eolFrom' => is_string($item['eolFrom'] ?? null) ? $item['eolFrom'] : null,
                'latest' => $latestName,
            ];
        }

        return $out === [] ? null : $out;
    }

    private function isCatalogPayload(mixed $cached): bool
    {
        if (! is_array($cached) || ! is_string($cached['fetched_at'] ?? null) || ! is_array($cached['products'] ?? null)) {
            return false;
        }

        foreach ($cached['products'] as $product) {
            if (! is_array($product) || ! is_array($product['releases'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
