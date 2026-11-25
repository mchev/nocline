<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Hosts\Models\Host;
use App\Modules\Updates\Services\ReleaseChecker;

/**
 * Optional self-update hint in API responses (ingest). Agents verify SHA256 before replacing the binary.
 */
class AgentUpdateOffer
{
    public function __construct(
        private readonly ReleaseChecker $releases,
        private readonly AgentBinary $binaries,
    ) {}

    /**
     * @return array{version: string, binaries: array<string, array{url: string, sha256: string}>}|null
     */
    public function forHost(Host $host, ?string $agentVersion): ?array
    {
        if (! config('phare.agent_auto_update') || ! $host->agent_auto_update) {
            return null;
        }

        if (! $this->releases->agentIsOutdated($agentVersion)) {
            return null;
        }

        $version = ltrim((string) config('phare.version'), 'vV');
        $binaries = [];

        foreach (AgentBinary::supportedArchitectures() as $arch) {
            $sha256 = $this->binaries->checksumSha256($arch);

            if ($sha256 === null || $sha256 === '') {
                return null;
            }

            $binaries[$arch] = [
                'url' => $this->binaries->releaseDownloadUrl($arch),
                'sha256' => $sha256,
            ];
        }

        return [
            'version' => $version,
            'binaries' => $binaries,
        ];
    }
}
