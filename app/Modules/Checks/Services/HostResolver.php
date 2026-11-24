<?php

namespace App\Modules\Checks\Services;

/**
 * Resolves a host name to IP addresses (swappable in tests).
 */
class HostResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_unique(array_filter($ips)));
    }
}
