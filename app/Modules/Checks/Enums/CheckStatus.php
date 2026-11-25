<?php

namespace App\Modules\Checks\Enums;

enum CheckStatus: string
{
    case Unknown = 'unknown';
    case Up = 'up';
    case Down = 'down';
    /** HTTP 502/503 — site in maintenance mode, not a hard outage. */
    case Degraded = 'degraded';
}
