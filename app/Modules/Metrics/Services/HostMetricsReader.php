<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use Illuminate\Support\Collection;

/**
 * Builds compact time series for host cards (CPU / RAM / disk).
 */
class HostMetricsReader
{
    /** Canonical agent keys, with demo aliases accepted for older simulators. */
    public const KEYS = [
        'cpu' => ['cpu.usage_pct', 'cpu.usage'],
        'mem' => ['mem.used_pct', 'mem.usage'],
        'disk' => ['disk.used_pct', 'disk.usage'],
    ];

    /**
     * @param  Collection<int, Host>|iterable<int, Host>  $hosts
     * @return array<int, array{cpu: list<float|null>, mem: list<float|null>, disk: list<float|null>, cpu_now: float|null, mem_now: float|null, disk_now: float|null}>
     */
    public function forHosts(iterable $hosts, int $points = 30): array
    {
        $hosts = Collection::make($hosts)->values();
        $ids = $hosts->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $since = now()->subMinutes($points)->startOfMinute();
        $wantedKeys = collect(self::KEYS)->flatten()->unique()->values()->all();

        $rows = MetricRollup::query()
            ->whereIn('host_id', $ids)
            ->where('bucket_size', MetricRollup::BUCKET_1M)
            ->whereIn('metric_key', $wantedKeys)
            ->where('bucket_start', '>=', $since)
            ->orderBy('bucket_start')
            ->get(['host_id', 'metric_key', 'bucket_start', 'avg']);

        $bucketStarts = [];

        for ($i = $points - 1; $i >= 0; $i--) {
            $bucketStarts[] = (int) now()->subMinutes($i)->startOfMinute()->timestamp;
        }

        $index = array_flip($bucketStarts);
        $byHost = [];

        foreach ($ids as $id) {
            $byHost[$id] = [
                'cpu' => array_fill(0, $points, null),
                'mem' => array_fill(0, $points, null),
                'disk' => array_fill(0, $points, null),
                'cpu_now' => null,
                'mem_now' => null,
                'disk_now' => null,
            ];
        }

        $keyAlias = [];

        foreach (self::KEYS as $short => $aliases) {
            foreach ($aliases as $alias) {
                $keyAlias[$alias] = $short;
            }
        }

        foreach ($rows as $row) {
            $short = $keyAlias[$row->metric_key] ?? null;

            if ($short === null || ! isset($byHost[$row->host_id])) {
                continue;
            }

            $ts = (int) $row->bucket_start->startOfMinute()->timestamp;
            $i = $index[$ts] ?? null;

            if ($i === null) {
                continue;
            }

            $avg = round((float) $row->avg, 1);
            $byHost[$row->host_id][$short][$i] = $avg;
            $byHost[$row->host_id][$short.'_now'] = $avg;
        }

        return $byHost;
    }
}
