<?php

namespace App\Modules\Alertes\Console;

use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Alertes\Services\AlertDispatcher;
use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:alerts:release-snoozes')]
#[Description('Clear expired alert snoozes and send any pending opened notifications')]
class ReleaseExpiredAlertSnoozes extends Command
{
    public function handle(AlertDispatcher $dispatcher): int
    {
        $released = 0;

        Check::query()
            ->whereNotNull('alerts_snoozed_until')
            ->where('alerts_snoozed_until', '<=', now())
            ->each(function (Check $check) use ($dispatcher, &$released): void {
                $check->update(['alerts_snoozed_until' => null]);
                $released++;

                $incident = Incident::query()
                    ->open()
                    ->where('check_id', $check->id)
                    ->first();

                if ($incident === null) {
                    return;
                }

                $hasOpened = AlertDelivery::query()
                    ->where('incident_id', $incident->id)
                    ->where('kind', 'opened')
                    ->where('status', 'sent')
                    ->exists();

                if (! $hasOpened) {
                    $dispatcher->dispatch($incident, 'opened');
                }
            });

        $this->components->info("{$released} snooze(s) released");

        return self::SUCCESS;
    }
}
