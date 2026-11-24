<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Hosts\Enums\CloudProvider;

/**
 * Best-effort cloud/hoster guess from a public IPv4 (Team Cymru DNS), used when the agent did not report metadata.
 */
class IpProviderLookup
{
    /** @var array<int, CloudProvider> */
    private const array ASN_MAP = [
        24940 => CloudProvider::Hetzner,
        213230 => CloudProvider::Hetzner,
        16509 => CloudProvider::Aws,
        14618 => CloudProvider::Aws,
        15169 => CloudProvider::Gcp,
        396982 => CloudProvider::Gcp,
        8075 => CloudProvider::Azure,
        14061 => CloudProvider::DigitalOcean,
        16276 => CloudProvider::Ovh,
        35540 => CloudProvider::Ovh,
        12876 => CloudProvider::Scaleway,
        20473 => CloudProvider::Vultr,
    ];

    public function providerForIp(string $ip): ?CloudProvider
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        if ($this->isPrivateOrReserved($ip)) {
            return null;
        }

        $asn = $this->asnForIpv4($ip);

        return $asn !== null ? (self::ASN_MAP[$asn] ?? null) : null;
    }

    private function asnForIpv4(string $ip): ?int
    {
        $octets = explode('.', $ip);

        if (count($octets) !== 4) {
            return null;
        }

        $query = implode('.', array_reverse($octets)).'.origin.asn.cymru.com';
        $records = @dns_get_record($query, DNS_TXT);

        if (! is_array($records) || $records === []) {
            return null;
        }

        $line = $records[0]['txt'] ?? '';
        $asn = (int) trim((string) strtok($line, '|'));

        return $asn > 0 ? $asn : null;
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
