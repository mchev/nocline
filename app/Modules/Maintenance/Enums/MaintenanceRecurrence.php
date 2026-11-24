<?php

namespace App\Modules\Maintenance\Enums;

enum MaintenanceRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Length of one repetition in days (0 for a one-off window).
     */
    public function periodDays(): int
    {
        return match ($this) {
            self::None => 0,
            self::Daily => 1,
            self::Weekly => 7,
        };
    }
}
