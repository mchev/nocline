<?php

namespace App\Modules\Metrics\Console;

use App\Modules\Metrics\Models\MetricRollup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:metrics:prune')]
#[Description('Delete metric rollups beyond their retention (1m: 7d, 5m: 90d, 1h: 2y)')]
class PruneMetricRollups extends Command
{
    /** @var array<int, int> bucket size (s) => retention (days) */
    public const RETENTION_DAYS = [
        MetricRollup::BUCKET_1M => 7,
        MetricRollup::BUCKET_5M => 90,
        MetricRollup::BUCKET_1H => 730,
    ];

    public function handle(): int
    {
        foreach (self::RETENTION_DAYS as $size => $days) {
            $deleted = MetricRollup::query()
                ->where('bucket_size', $size)
                ->where('bucket_start', '<', now()->subDays($days))
                ->delete();

            $this->components->info("bucket {$size}s: {$deleted} deleted");
        }

        return self::SUCCESS;
    }
}
