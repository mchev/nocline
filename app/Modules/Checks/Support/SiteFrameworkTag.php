<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;

final class SiteFrameworkTag
{
    public static function fromCheck(Check $check): ?SiteFramework
    {
        foreach (SiteFramework::taggable() as $value) {
            if (in_array($value, $check->tags ?? [], true)) {
                return SiteFramework::from($value);
            }
        }

        return null;
    }

    public static function hasFrameworkTag(Check $check): bool
    {
        return self::fromCheck($check) !== null;
    }
}
