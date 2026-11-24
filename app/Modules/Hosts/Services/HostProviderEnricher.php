<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Hosts\Enums\CloudProvider;
use App\Modules\Hosts\Models\Host;

/**
 * Fills {@see Host::$agent_meta} `provider` via ASN lookup when the agent did not detect cloud metadata.
 */
class HostProviderEnricher
{
    public function __construct(
        private readonly IpProviderLookup $lookup,
        private readonly HostMetadataApplier $metadata,
    ) {}

    public function enrich(Host $host): bool
    {
        $meta = $host->agent_meta ?? [];

        if (($meta['provider_source'] ?? null) === 'agent' && ! empty($meta['provider'])) {
            return false;
        }

        $ip = $meta['public_ipv4'] ?? null;

        if (! is_string($ip) || $ip === '') {
            return false;
        }

        $provider = $this->lookup->providerForIp($ip);

        if ($provider === null) {
            return false;
        }

        if (! $this->metadata->apply($host, [
            'provider' => $provider->value,
            'provider_source' => 'whois',
        ])) {
            return false;
        }

        $host->save();

        return true;
    }

    public function labelForHost(Host $host): ?string
    {
        $value = $host->agent_meta['provider'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        $case = CloudProvider::tryFrom($value);

        return $case?->label();
    }
}
