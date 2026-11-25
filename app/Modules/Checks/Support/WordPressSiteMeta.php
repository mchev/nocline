<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Models\Check;

/**
 * WordPress metadata discovered remotely (core version, last scan).
 */
final class WordPressSiteMeta
{
    public static function version(Check $check): ?string
    {
        $version = $check->config['wordpress']['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    public static function detectedAtIso(Check $check): ?string
    {
        $at = $check->config['wordpress']['detected_at'] ?? null;

        return is_string($at) && $at !== '' ? $at : null;
    }
}
