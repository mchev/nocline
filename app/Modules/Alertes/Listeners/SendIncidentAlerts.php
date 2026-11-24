<?php

namespace App\Modules\Alertes\Listeners;

use App\Modules\Alertes\Services\AlertDispatcher;
use App\Modules\Incidents\Events\IncidentOpened;
use App\Modules\Incidents\Events\IncidentResolved;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendIncidentAlerts implements ShouldQueue
{
    public function __construct(private AlertDispatcher $dispatcher) {}

    public function handle(IncidentOpened|IncidentResolved $event): void
    {
        $this->dispatcher->dispatch(
            $event->incident,
            $event instanceof IncidentResolved ? 'resolved' : 'opened',
        );
    }
}
