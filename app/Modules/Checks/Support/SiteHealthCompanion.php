<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Models\Check;

final class SiteHealthCompanion
{
    public const string NAME_SUFFIX = ' — health';

    /**
     * @return array{health_check_id?: int, is_health_companion?: bool, companion_of?: int}
     */
    public static function linkMeta(Check $check): array
    {
        $config = is_array($check->config) ? $check->config : [];

        $meta = [];

        if (isset($config['health_check_id'])) {
            $meta['health_check_id'] = (int) $config['health_check_id'];
        }

        if (isset($config['companion_of'])) {
            $meta['companion_of'] = (int) $config['companion_of'];
            $meta['is_health_companion'] = true;
        }

        if (($meta['is_health_companion'] ?? false) === false && self::looksLikeHealthCompanion($check)) {
            $meta['is_health_companion'] = true;
        }

        return $meta;
    }

    public static function looksLikeHealthCompanion(Check $check): bool
    {
        $config = is_array($check->config) ? $check->config : [];

        if (isset($config['companion_of'])) {
            return true;
        }

        return str_ends_with($check->name, self::NAME_SUFFIX)
            && str_ends_with($check->target, '/up');
    }
}
