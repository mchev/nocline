<?php

namespace App\Modules\Hosts\Services;

/**
 * phare-agent Linux binaries: published on the official GitHub repo (see .github/workflows/release.yml).
 * Hubs never need Go — monitored servers download from those releases (version = hub VERSION file).
 */
class AgentBinary
{
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
}
