<?php

namespace App\Modules\Checks\Enums;

enum SiteFramework: string
{
    case Laravel = 'laravel';
    case WordPress = 'wordpress';
    case Unknown = 'unknown';

    /**
     * Frameworks that can be attached to a check as a tag.
     *
     * @return array<int, string>
     */
    public static function taggable(): array
    {
        return [self::Laravel->value, self::WordPress->value];
    }
}
