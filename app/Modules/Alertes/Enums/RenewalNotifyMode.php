<?php

namespace App\Modules\Alertes\Enums;

enum RenewalNotifyMode: string
{
    case Off = 'off';
    case ExpiredOnly = 'expired_only';
    case Thresholds = 'thresholds';
}
