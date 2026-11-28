<?php

namespace App\Modules\Hosts\Support;

use App\Modules\Hosts\Enums\CloudProvider;
use App\Modules\Hosts\Models\Host;
use Illuminate\Support\Carbon;

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
     *     kernel: ?string,
     *     stack: array<string, string>|null,
     *     stack_warnings: array<string, string>,
     *     reboot_required: bool,
     *     reboot_required_since: ?string,
     *     reboot_required_message: ?string
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

        $stack = HostStackRules::normalize(is_array($meta['stack'] ?? null) ? $meta['stack'] : null);
        $rebootRequired = (bool) ($meta['reboot_required'] ?? false);
        $rebootSince = is_string($meta['reboot_required_since'] ?? null)
            ? $meta['reboot_required_since']
            : null;

        return [
            'hostname' => $meta['hostname'] ?? null,
            'public_ipv4' => $meta['public_ipv4'] ?? null,
            'distro' => $distro,
            'provider' => is_string($provider) ? $provider : null,
            'provider_label' => $label,
            'provider_region' => $meta['provider_region'] ?? null,
            'kernel' => $meta['kernel'] ?? null,
            'stack' => $stack,
            'stack_warnings' => app(HostStackVersionAdvisor::class)->warnings($stack),
            'reboot_required' => $rebootRequired,
            'reboot_required_since' => $rebootSince,
            'reboot_required_message' => self::rebootMessage($rebootRequired, $rebootSince),
        ];
    }

    private static function rebootMessage(bool $required, ?string $sinceIso): ?string
    {
        if (! $required) {
            return null;
        }

        if ($sinceIso === null || $sinceIso === '') {
            return __('A reboot is required to apply a kernel or system update.');
        }

        try {
            $since = Carbon::parse($sinceIso);
        } catch (\Throwable) {
            return __('A reboot is required to apply a kernel or system update.');
        }

        return __('A reboot is required to apply a kernel or system update (pending since :since).', [
            'since' => $since->timezone(config('app.timezone'))->isoFormat('LLL'),
        ]);
    }
}
