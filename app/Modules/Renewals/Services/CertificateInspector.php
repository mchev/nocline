<?php

namespace App\Modules\Renewals\Services;

use Carbon\CarbonImmutable;

/**
 * Reads a server certificate's expiry (swappable in tests).
 */
class CertificateInspector
{
    public function expiresAt(string $host, int $port = 443, int $timeout = 10): ?CarbonImmutable
    {
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $client = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);
        $parsed = $cert ? openssl_x509_parse($cert) : false;

        return $parsed ? CarbonImmutable::createFromTimestamp($parsed['validTo_time_t']) : null;
    }
}
