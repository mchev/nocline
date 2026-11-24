<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Models\Renewal;

/**
 * SSL badge for HTTPS website checks (renewals scan, not per-page load).
 */
class WebsiteSslStatus
{
    /**
     * @return 'ok'|'warning'|'expired'|'error'|'unknown'|null null when not applicable (non-HTTPS site)
     */
    public static function forCheck(Check $check, ?Renewal $sslRenewal): ?string
    {
        if ($check->type !== CheckType::Http || $check->category !== CheckCategory::Website) {
            return null;
        }

        $scheme = parse_url($check->target, PHP_URL_SCHEME);

        if (strtolower((string) $scheme) !== 'https') {
            return null;
        }

        if ($sslRenewal === null) {
            return 'unknown';
        }

        if ($sslRenewal->check_error !== null) {
            return 'error';
        }

        $days = $sslRenewal->daysRemaining();

        return match (true) {
            $days < 0 => 'expired',
            $days <= 14 => 'warning',
            default => 'ok',
        };
    }
}
