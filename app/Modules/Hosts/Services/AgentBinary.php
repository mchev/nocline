<?php

namespace App\Modules\Hosts\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * phare-agent Linux binaries: published on the official GitHub repo (see .github/workflows/release.yml).
 * Hubs never need Go — monitored servers download from those releases (version = hub VERSION file).
 */
class AgentBinary
{
    private const CHECKSUM_CACHE_DAYS = 7;

    /**
     * @return list<'amd64'|'arm64'>
     */
    public static function supportedArchitectures(): array
    {
        return ['amd64', 'arm64'];
    }

    public function releaseTag(): string
    {
        $version = trim((string) config('phare.version'));

        if ($version === '') {
            return 'v0.0.0';
        }

        return str_starts_with($version, 'v') ? $version : 'v'.$version;
    }

    public function releaseDownloadUrl(string $arch): string
    {
        $custom = config('phare.agent_download_url');
        if (is_string($custom) && $custom !== '') {
            return str_replace('{arch}', $arch, $custom);
        }

        return sprintf(
            'https://github.com/%s/releases/download/%s/phare-agent-linux-%s',
            config('phare.official_repository'),
            $this->releaseTag(),
            $arch,
        );
    }

    /**
     * Prefix for the install script: curl/wget append $ARCH (amd64|arm64).
     */
    public function releaseDownloadPrefix(): string
    {
        $custom = config('phare.agent_download_url');
        if (is_string($custom) && $custom !== '') {
            $needle = '{arch}';
            $pos = strpos($custom, $needle);
            if ($pos !== false) {
                return substr($custom, 0, $pos);
            }

            return $custom;
        }

        return sprintf(
            'https://github.com/%s/releases/download/%s/phare-agent-linux-',
            config('phare.official_repository'),
            $this->releaseTag(),
        );
    }

    /**
     * Optional local override after `scripts/build-agent.sh` (development only).
     */
    public function localPath(string $arch): ?string
    {
        $path = rtrim((string) config('phare.agent_binary_dir'), '/')."/phare-agent-linux-{$arch}";

        return is_file($path) ? $path : null;
    }

    public function checksumSidecarUrl(string $arch): string
    {
        $custom = config('phare.agent_download_url');
        if (is_string($custom) && $custom !== '') {
            $needle = '{arch}';
            $pos = strpos($custom, $needle);
            if ($pos !== false) {
                return substr($custom, 0, $pos).$arch.'.sha256';
            }

            return $custom.'.sha256';
        }

        return sprintf(
            'https://github.com/%s/releases/download/%s/phare-agent-linux-%s.sha256',
            config('phare.official_repository'),
            $this->releaseTag(),
            $arch,
        );
    }

    /** Lowercase hex SHA-256 of the release binary, or null when unavailable. */
    public function checksumSha256(string $arch): ?string
    {
        if ($local = $this->localPath($arch)) {
            $sidecar = $local.'.sha256';
            if (is_file($sidecar)) {
                return $this->normalizeHash((string) file_get_contents($sidecar));
            }

            return hash_file('sha256', $local) ?: null;
        }

        $cacheKey = 'phare:agent-sha256:'.config('phare.version').':'.$arch;

        return Cache::remember($cacheKey, now()->addDays(self::CHECKSUM_CACHE_DAYS), function () use ($arch): ?string {
            try {
                $response = Http::timeout(15)
                    ->withUserAgent('phare/'.config('phare.version'))
                    ->get($this->checksumSidecarUrl($arch));
            } catch (Throwable) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $hash = $this->normalizeHash($response->body());

            return strlen($hash) === 64 ? $hash : null;
        });
    }

    private function normalizeHash(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = strtok($raw, " \t\r\n") ?: '';

        return preg_match('/^[a-f0-9]{64}$/', $raw) === 1 ? $raw : '';
    }
}
