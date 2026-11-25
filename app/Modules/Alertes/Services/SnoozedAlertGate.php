<?php

namespace App\Modules\Alertes\Services;

use App\Modules\Checks\Models\Check;

class SnoozedAlertGate
{
    public function isMuted(Check $check): bool
    {
        return $check->alerts_snoozed_until !== null
            && $check->alerts_snoozed_until->isFuture();
    }
}
