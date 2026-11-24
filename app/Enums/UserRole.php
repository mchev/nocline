<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Tech = 'tech';
    case Viewer = 'viewer';

    public function canManage(): bool
    {
        return $this === self::Admin || $this === self::Tech;
    }
}
