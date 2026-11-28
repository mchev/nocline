<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Hosts\Models\Host;

/**
 * Merges agent-pushed host facts into {@see Host::$agent_meta} (mastered snapshot, not per-heartbeat rows).
 */
class HostMetadataApplier
{
    /**
     * @param  array<string, mixed>  $incoming
     */
    public function apply(Host $host, array $incoming): bool
    {
        $incoming = array_filter($incoming, static fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($incoming === []) {
            return false;
        }

        $existing = $host->agent_meta ?? [];
        $merged = array_merge($existing, $incoming);

        if (($incoming['provider'] ?? null) !== null) {
            $merged['provider_source'] = $incoming['provider_source'] ?? 'agent';
        }

        if (array_key_exists('reboot_required', $incoming) && ! $incoming['reboot_required']) {
            unset($merged['reboot_required_since']);
            $merged['reboot_required'] = false;
        }

        $host->forceFill([
            'agent_meta' => $merged,
            'agent_meta_at' => now(),
        ]);

        if (! empty($incoming['distro']) && is_string($incoming['distro'])) {
            $host->forceFill(['os' => mb_substr($incoming['distro'], 0, 64)]);
        }

        return $host->isDirty(['agent_meta', 'agent_meta_at', 'os']);
    }
}
