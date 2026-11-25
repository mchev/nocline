<?php

namespace App\Modules\Incidents\Listeners;

use App\Modules\Checks\Events\CheckWentDegraded;
use App\Modules\Incidents\Events\IncidentOpened;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;

class OpenIncidentOnCheckDegraded
{
    public function __construct(private MaintenanceChecker $maintenance) {}

    public function handle(CheckWentDegraded $event): void
    {
        $existing = Incident::query()->open()->where('check_id', $event->check->id)->first();

        if ($existing !== null) {
            $existing->update(['last_error' => $event->error]);

            return;
        }

        if ($this->maintenance->isActive($event->check)) {
            return;
        }

        $incident = Incident::create([
            'check_id' => $event->check->id,
            'title' => $event->check->name.' — '.__('HTTP maintenance'),
            'started_at' => now(),
            'last_error' => $event->error,
        ]);

        IncidentOpened::dispatch($incident);
    }
}
