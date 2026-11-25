<?php

namespace App\Modules\Maintenance\Console;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Events\CheckWentDown;
use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:maintenance:catch-up')]
#[Description('Open the incident of monitors still down once their maintenance is over')]
class OpenIncidentsAfterMaintenance extends Command
{
    public function handle(MaintenanceChecker $maintenance): int
    {
        $stillDown = Check::query()
            ->where('enabled', true)
            ->whereHas('state', fn ($q) => $q->where('status', CheckStatus::Down->value))
            ->whereNotIn('id', Incident::query()->open()->select('check_id'))
            ->with('state')
            ->get();

        $inMaintenance = array_flip($maintenance->activeCheckIds($stillDown));
        $opened = 0;

        foreach ($stillDown as $check) {
            if (! isset($inMaintenance[$check->id])) {
                CheckWentDown::dispatch($check, $check->state?->last_error);
                $opened++;
            }
        }

        $this->components->info("{$opened} incident(s) opened");

        return self::SUCCESS;
    }
}
