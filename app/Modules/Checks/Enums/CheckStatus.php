<?php

namespace App\Modules\Checks\Enums;

enum CheckStatus: string
{
    case Unknown = 'unknown';
    case Up = 'up';
    case Down = 'down';
}
