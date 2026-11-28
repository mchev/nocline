<?php

namespace App\Modules\Hosts\Support;

use App\Modules\Hosts\Enums\CloudProvider;
use Illuminate\Validation\Rule;

final class HostMetadataRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = 'host_meta'): array
    {
        $key = fn (string $field): string => $prefix === '' ? $field : "{$prefix}.{$field}";

        return array_merge([
            $prefix => ['nullable', 'array'],
            $key('hostname') => ['nullable', 'string', 'max:255'],
            $key('public_ipv4') => ['nullable', 'ipv4'],
            $key('ipv4s') => ['nullable', 'array', 'max:32'],
            $key('ipv4s.*') => ['ipv4'],
            $key('distro') => ['nullable', 'string', 'max:128'],
            $key('kernel') => ['nullable', 'string', 'max:64'],
            $key('provider') => ['nullable', 'string', 'max:32', Rule::in(CloudProvider::values())],
            $key('provider_region') => ['nullable', 'string', 'max:64'],
            $key('provider_source') => ['nullable', 'string', 'max:16', Rule::in(['agent', 'whois'])],
            $key('reboot_required') => ['nullable', 'boolean'],
            $key('reboot_required_since') => ['nullable', 'date'],
        ], HostStackRules::validationRules("{$prefix}.stack"));
    }
}
