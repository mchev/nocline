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
     *     provider_region: ?string,
     *     kernel: ?string
     * }
     */
    public static function forHost(Host $host): array
    {
        $meta = $host->agent_meta ?? [];
        $provider = $meta['provider'] ?? null;
        $label = is_string($provider) ? CloudProvider::tryFrom($provider)?->label() : null;
        $distro = $meta['distro'] ?? null;

        if (($distro === null || $distro === '') && is_string($host->os) && $host->os !== '') {
            $distro = $host->os;
        }

        return [
            'hostname' => $meta['hostname'] ?? null,
            'public_ipv4' => $meta['public_ipv4'] ?? null,
            'distro' => $distro,
            'provider' => is_string($provider) ? $provider : null,
            'provider_label' => $label,
            'provider_region' => $meta['provider_region'] ?? null,
            'kernel' => $meta['kernel'] ?? null,
        ];
    }
}
