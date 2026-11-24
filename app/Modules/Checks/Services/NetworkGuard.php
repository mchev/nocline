<?php

namespace App\Modules\Checks\Services;

/**
 * Decides whether the hub may contact an address on behalf of a user (SSRF protection).
 */
class NetworkGuard
{
    /**
     * Ranges that are never public (IPv4 and IPv6): loopback, private, link-local/metadata, CGNAT, reserved, multicast...
     *
     * @var array<int, string>
     */
    private const array BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8',
    ];

    /**
     * Whether the address is a public one. IPv4 embedded in IPv6 (mapped, NAT64, 6to4) is judged on the IPv4 part.
     */
    public function isPublicAddress(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16) {
            $embedded = $this->embeddedIpv4($packed);

            if ($embedded !== null) {
                return $this->isPublicAddress($embedded);
            }
        }

        foreach (self::BLOCKED as $cidr) {
            if ($this->inRange($packed, $cidr)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a URL host is a numeric spelling of an IPv4 address that is not plain dotted-quad
     * (decimal "2130706433", hex "0x7f000001", octal "0177.0.0.1", short "127.1"): refused, never interpreted.
     */
    public function isObfuscatedIpv4(string $host): bool
    {
        return preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+))*$/i', $host) === 1
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false;
    }

    private function embeddedIpv4(string $packed): ?string
    {
        $isMapped = str_starts_with($packed, str_repeat("\0", 10)."\xff\xff");
        $isCompatible = str_starts_with($packed, str_repeat("\0", 12)) && $packed !== str_repeat("\0", 16) && $packed !== str_repeat("\0", 15)."\x01";
        $isNat64 = str_starts_with($packed, hex2bin('0064ff9b').str_repeat("\0", 8));

        if ($isMapped || $isCompatible || $isNat64) {
            return inet_ntop(substr($packed, 12, 4)) ?: null;
        }

        if (str_starts_with($packed, "\x20\x02")) {
            return inet_ntop(substr($packed, 2, 4)) ?: null;
        }

        return null;
    }

    private function inRange(string $packed, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $network = (string) inet_pton($subnet);

        if (strlen($network) !== strlen($packed)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);

        if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        $remaining = $bits % 8;

        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
