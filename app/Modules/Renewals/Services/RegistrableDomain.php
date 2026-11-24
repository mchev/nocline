<?php

namespace App\Modules\Renewals\Services;

/**
 * Derives the registrable domain (example.co.uk) from a hostname, using a short built-in list of multi-part suffixes.
 */
class RegistrableDomain
{
    /** @var array<int, string> */
    private const MULTI_PART_SUFFIXES = [
        'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'ac.uk', 'gov.uk',
        'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au',
        'co.nz', 'org.nz', 'net.nz',
        'co.za', 'org.za',
        'co.jp', 'or.jp', 'ne.jp',
        'com.br', 'net.br', 'org.br',
        'com.mx', 'com.ar', 'com.tr', 'com.cn', 'net.cn', 'org.cn',
        'co.in', 'net.in', 'org.in',
        'co.kr', 'com.sg', 'com.hk', 'com.tw', 'com.pl', 'com.es', 'com.pt',
    ];

    /** @var array<int, string> */
    private const UNSUPPORTED_SUFFIXES = ['test', 'local', 'localhost', 'example', 'invalid', 'internal', 'lan', 'home', 'corp', 'intranet'];

    public static function from(?string $host): ?string
    {
        $host = strtolower(trim((string) $host, " \t\n\r\0\x0B."));
        $host = trim($host, '[]');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            return null;
        }

        $labels = explode('.', $host);
        $tld = end($labels);

        if (in_array($tld, self::UNSUPPORTED_SUFFIXES, true) || is_numeric($tld)) {
            return null;
        }

        if (in_array($host, self::MULTI_PART_SUFFIXES, true)) {
            return null;
        }

        $take = 2;

        if (count($labels) > 2 && in_array(implode('.', array_slice($labels, -2)), self::MULTI_PART_SUFFIXES, true)) {
            $take = 3;
        }

        if (count($labels) < $take) {
            return null;
        }

        return implode('.', array_slice($labels, -$take));
    }
}
