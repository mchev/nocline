<?php

namespace App\Modules\Checks\Console;

use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Models\CheckLatencySample;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:checks:prune {--event-days=400} {--delivery-days=180} {--sample-days=90}')]
#[Description('Purge old check events and alert deliveries (storage stays bounded)')]
class PruneCheckHistory extends Command
{
    public function handle(): int
    {
        $events = CheckEvent::query()->where('at', '<', now()->subDays((int) $this->option('event-days')))->delete();
        $deliveries = AlertDelivery::query()->where('created_at', '<', now()->subDays((int) $this->option('delivery-days')))->delete();
        $samples = CheckLatencySample::query()->where('at', '<', now()->subDays((int) $this->option('sample-days')))->delete();

        $this->components->info("{$events} event(s), {$deliveries} delivery record(s), {$samples} latency sample(s) deleted");

        return self::SUCCESS;
    }
}
