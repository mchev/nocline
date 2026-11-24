<?php

namespace App\Modules\Hosts\Support;

use App\Modules\Hosts\Enums\CloudProvider;
use App\Modules\Hosts\Models\Host;

final class HostMetaPresenter
{
    /**
     * @return array{
     *     hostname: ?string,
     *     public_ipv4: ?string,
     *     distro: ?string,
     *     provider: ?string,
     *     provider_label: ?string,
     *     provider_region: ?string
     * }
     */
    public static function forHost(Host $host): array
    {
        $meta = $host->agent_meta ?? [];
        $provider = $meta['provider'] ?? null;
        $label = is_string($provider) ? CloudProvider::tryFrom($provider)?->label() : null;

        return [
            'hostname' => $meta['hostname'] ?? null,
            'public_ipv4' => $meta['public_ipv4'] ?? null,
            'distro' => $meta['distro'] ?? null,
            'provider' => is_string($provider) ? $provider : null,
            'provider_label' => $label,
            'provider_region' => $meta['provider_region'] ?? null,
        ];
    }
}
