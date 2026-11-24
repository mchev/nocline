<?php

namespace App\Support;

use App\Events\OpsSnapshotUpdated;
use Illuminate\Support\Facades\Cache;

/**
 * Throttled fan-out so ingest/simulate ticks don't spam every open tab.
 */
class OpsBroadcast
{
    public static function ping(int $throttleSeconds = 2): void
    {
        if (! Cache::add('ops:broadcast:lock', 1, $throttleSeconds)) {
            return;
        }

        event(new OpsSnapshotUpdated);
    }
}
