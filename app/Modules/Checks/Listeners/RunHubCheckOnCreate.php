<?php

namespace App\Modules\Checks\Listeners;

use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Jobs\ExecuteHubCheck;

class RunHubCheckOnCreate
{
    public function handle(CheckCreated $event): void
    {
        if (! config('nocline.eager_hub_check_on_create')) {
            return;
        }

        if ($event->check->runsOnProbes()) {
            return;
        }

        ExecuteHubCheck::dispatch($event->check->id)->afterResponse();
    }
}
