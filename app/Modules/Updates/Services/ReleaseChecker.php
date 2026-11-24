<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Compares the installed version with the latest GitHub release. The result is cached: web requests
 * only ever read the cache, the daily `phare:update:check` command is the only thing that calls GitHub.
 */
class ReleaseChecker
{
    private const CACHE_KEY = 'phare:latest-release';

    /**
     * Ask GitHub for the latest release and cache it. Returns null when the check is disabled or fails.
     *
     * @return array{version: string, url: string, notes: string, published_at: string|null}|null
     */
    public function refresh(): ?array
    {
        $repository = config('phare.repository');

        if (! config('phare.update_check') || ! is_string($repository) || $repository === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()->timeout(10)->withUserAgent('phare/'.config('phare.version'))
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_string($response->json('tag_name'))) {
            return null;
        }

        $release = [
            'version' => ltrim($response->json('tag_name'), 'vV'),
            'url' => (string) $response->json('html_url'),
            'notes' => mb_substr((string) $response->json('body'), 0, 4000),
            'published_at' => $response->json('published_at'),
        ];

        Cache::put(self::CACHE_KEY, $release, now()->addDays(3));

        return $release;
    }

    /**
     * The cached latest release when it is newer than the installed version, otherwise null.
     *
     * @return array{version: string, url: string, notes: string, published_at: string|null}|null
     */
    public function available(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached) || ! config('phare.update_check') || ! is_string($cached['version'] ?? null)) {
            return null;
        }

        $release = [
            'version' => $cached['version'],
            'url' => (string) ($cached['url'] ?? ''),
            'notes' => (string) ($cached['notes'] ?? ''),
            'published_at' => is_string($cached['published_at'] ?? null) ? $cached['published_at'] : null,
        ];

        return version_compare($release['version'], (string) config('phare.version'), '>') ? $release : null;
    }

    /**
     * Whether an agent reporting $agentVersion is older than this hub.
     */
    public function agentIsOutdated(?string $agentVersion): bool
    {
        return $agentVersion !== null
            && preg_match('/^\d+\.\d+/', $agentVersion) === 1
            && version_compare($agentVersion, (string) config('phare.version'), '<');
    }
}
