<?php

namespace App\Modules\Incidents\Listeners;

use App\Modules\Checks\Events\CheckRecovered;
use App\Modules\Incidents\Events\IncidentResolved;
use App\Modules\Incidents\Models\Incident;

class ResolveIncidentOnCheckRecovered
{
    public function handle(CheckRecovered $event): void
    {
        Incident::query()->open()->where('check_id', $event->check->id)->each(function (Incident $incident): void {
            $incident->update(['resolved_at' => now()]);
            IncidentResolved::dispatch($incident);
        });
    }
}
