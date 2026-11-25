<?php

namespace App\Modules\Checks\Support;

/**
 * HTTP 502/503 often mean “maintenance mode” (e.g. WordPress) rather than a hard outage.
 */
final class HttpServiceUnavailable
{
    private const CODES = [502, 503];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function isMaintenanceResponse(?string $error, array $metadata = []): bool
    {
        $code = $metadata['http_status'] ?? null;

        if (is_int($code) && in_array($code, self::CODES, true)) {
            return true;
        }

        if (is_string($code) && ctype_digit($code) && in_array((int) $code, self::CODES, true)) {
            return true;
        }

        if ($error === null || $error === '') {
            return false;
        }

        return (bool) preg_match('/^HTTP (502|503)\b/', $error);
    }
}
