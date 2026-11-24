<?php

namespace App\Modules\Metrics\Console;

use App\Modules\Metrics\Models\MetricRollup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:metrics:rollup')]
#[Description('Aggregate completed 1m rollups into 5m and 5m into 1h (idempotent, recomputes the last 3 buckets)')]
class RollupMetrics extends Command
{
    /** @var array<int, int> source bucket size => destination bucket size */
    private const STEPS = [
        MetricRollup::BUCKET_1M => MetricRollup::BUCKET_5M,
        MetricRollup::BUCKET_5M => MetricRollup::BUCKET_1H,
    ];

    public function handle(): int
    {
        foreach (self::STEPS as $source => $destination) {
            $written = $this->rollup($source, $destination);
            $this->components->info("{$source}s -> {$destination}s: {$written} bucket(s)");
        }

        return self::SUCCESS;
    }

    private function rollup(int $source, int $destination): int
    {
        $until = intdiv((int) now()->timestamp, $destination) * $destination;
        $from = $until - 3 * $destination;

        $groups = MetricRollup::query()
            ->where('bucket_size', $source)
            ->where('bucket_start', '>=', now()->setTimestamp($from))
            ->where('bucket_start', '<', now()->setTimestamp($until))
            ->get()
            ->groupBy(fn (MetricRollup $r) => $r->host_id.'|'.$r->metric_key.'|'.intdiv((int) $r->bucket_start->timestamp, $destination) * $destination);

        foreach ($groups as $key => $rows) {
            [$hostId, $metric, $start] = explode('|', $key);
            $samples = $rows->sum('sample_count');

            MetricRollup::query()->updateOrCreate(
                [
                    'host_id' => (int) $hostId,
                    'metric_key' => $metric,
                    'bucket_size' => $destination,
                    'bucket_start' => now()->setTimestamp((int) $start),
                ],
                [
                    'min' => $rows->min('min'),
                    'max' => $rows->max('max'),
                    'avg' => $rows->sum(fn (MetricRollup $r) => $r->avg * $r->sample_count) / max($samples, 1),
                    'p95' => $rows->max('p95'),
                    'sample_count' => $samples,
                ],
            );
        }

        return $groups->count();
    }
}
