<?php

namespace App\Modules\Checks\Listeners;

use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Jobs\ExecuteHubCheck;

class RunHubCheckOnCreate
{
    public function handle(CheckCreated $event): void
    {
        if ($event->check->runsOnProbes()) {
            return;
        }

        ExecuteHubCheck::dispatch($event->check->id)->afterResponse();
    }
}
