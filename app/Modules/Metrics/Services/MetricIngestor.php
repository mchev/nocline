<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Merges agent-reduced metric windows into 1-minute rollups (never raw rows).
 */
class MetricIngestor
{
    /**
     * @param  array<int, array{key: string, min?: float|int|null, max?: float|int|null, avg: float|int, p95?: float|int|null}>  $metrics
     */
    public function ingest(Host $host, array $metrics, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $bucketStart = $at->startOfMinute();

        DB::transaction(function () use ($host, $metrics, $bucketStart): void {
            foreach ($metrics as $metric) {
                $this->merge($host, $metric, $bucketStart);
            }
        });
    }

    /**
     * @param  array{key: string, min?: float|int|null, max?: float|int|null, avg: float|int, p95?: float|int|null}  $metric
     */
    private function merge(Host $host, array $metric, CarbonInterface $bucketStart): void
    {
        $avg = (float) $metric['avg'];
        $min = isset($metric['min']) ? (float) $metric['min'] : $avg;
        $max = isset($metric['max']) ? (float) $metric['max'] : $avg;
        $p95 = isset($metric['p95']) ? (float) $metric['p95'] : $max;

        $rollup = MetricRollup::query()->firstOrNew([
            'host_id' => $host->id,
            'metric_key' => $metric['key'],
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $bucketStart,
        ]);

        if (! $rollup->exists) {
            $rollup->fill(['min' => $min, 'max' => $max, 'avg' => $avg, 'p95' => $p95, 'sample_count' => 1])->save();

            return;
        }

        $count = $rollup->sample_count;
        $rollup->fill([
            'min' => min($rollup->min ?? $min, $min),
            'max' => max($rollup->max ?? $max, $max),
            'avg' => (($rollup->avg * $count) + $avg) / ($count + 1),
            'p95' => max($rollup->p95 ?? $p95, $p95),
            'sample_count' => $count + 1,
        ])->save();
    }
}
