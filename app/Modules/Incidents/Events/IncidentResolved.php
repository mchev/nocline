<?php

namespace App\Modules\Incidents\Events;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Foundation\Events\Dispatchable;

class IncidentResolved
{
    use Dispatchable;

    public function __construct(public Incident $incident) {}
}
