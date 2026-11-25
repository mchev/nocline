<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * @return array<int, array{cpu: list<float|null>, mem: list<float|null>, disk: list<float|null>, cpu_now: float|null, mem_now: float|null, disk_now: float|null, disk_used_bytes: float|null, disk_total_bytes: float|null}>
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
                'disk_used_bytes' => null,
                'disk_total_bytes' => null,
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

        $diskBytes = $this->latestDiskBytes($ids);

        foreach ($ids as $id) {
            $byHost[$id]['disk_used_bytes'] = $diskBytes[$id]['disk_used_bytes'] ?? null;
            $byHost[$id]['disk_total_bytes'] = $diskBytes[$id]['disk_total_bytes'] ?? null;
        }

        /** @var array<int, array{cpu: list<float|null>, mem: list<float|null>, disk: list<float|null>, cpu_now: float|null, mem_now: float|null, disk_now: float|null, disk_used_bytes: float|null, disk_total_bytes: float|null}> $byHost */
        return $byHost;
    }

    /**
     * Latest disk capacity (from the most recent 1m rollups, agent push).
     *
     * @param  array<int, int>  $hostIds
     * @return array<int, array{disk_used_bytes: float|null, disk_total_bytes: float|null}>
     */
    public function latestDiskBytes(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        $rows = MetricRollup::query()
            ->whereIn('host_id', $hostIds)
            ->whereIn('metric_key', ['disk.used_bytes', 'disk.total_bytes'])
            ->where('bucket_size', MetricRollup::BUCKET_1M)
            ->where('bucket_start', '>=', now()->subMinutes(30))
            ->orderByDesc('bucket_start')
            ->get(['host_id', 'metric_key', 'avg']);

        $out = array_fill_keys($hostIds, [
            'disk_used_bytes' => null,
            'disk_total_bytes' => null,
        ]);

        foreach ($rows as $row) {
            $id = $row->host_id;
            $key = $row->metric_key === 'disk.used_bytes' ? 'disk_used_bytes' : 'disk_total_bytes';

            if ($out[$id][$key] === null) {
                $out[$id][$key] = round((float) $row->avg, 1);
            }
        }

        return $out;
    }

    /**
     * CPU / RAM / disk usage (%) over a window for the host detail chart (rollup-backed).
     *
     * @return array{
     *     hours: int,
     *     bucket_seconds: int,
     *     end_at: string,
     *     points: list<array{at: string, cpu: float|null, mem: float|null, disk: float|null}>
     * }
     */
    public function chart(Host $host, int $hours, ?CarbonInterface $end = null): array
    {
        $hours = max(1, min(720, $hours));
        $end = CarbonImmutable::parse($end ?? now())->startOfSecond();
        $bucketSize = $this->chartBucketSize($hours);
        $endBucket = $this->alignTimestamp((int) $end->timestamp, $bucketSize);
        $startBucket = $endBucket - ($hours * 3600);
        $startBucket = $this->alignTimestamp($startBucket, $bucketSize);

        $wantedKeys = collect(self::KEYS)->flatten()->unique()->values()->all();

        $rows = MetricRollup::query()
            ->where('host_id', $host->id)
            ->where('bucket_size', $bucketSize)
            ->whereIn('metric_key', $wantedKeys)
            ->where('bucket_start', '>=', CarbonImmutable::createFromTimestamp($startBucket))
            ->where('bucket_start', '<=', CarbonImmutable::createFromTimestamp($endBucket))
            ->orderBy('bucket_start')
            ->get(['metric_key', 'bucket_start', 'avg']);

        $keyAlias = [];

        foreach (self::KEYS as $short => $aliases) {
            foreach ($aliases as $alias) {
                $keyAlias[$alias] = $short;
            }
        }

        $byTs = [];

        foreach ($rows as $row) {
            $short = $keyAlias[$row->metric_key] ?? null;

            if ($short === null) {
                continue;
            }

            $ts = $this->alignTimestamp((int) $row->bucket_start->timestamp, $bucketSize);

            if (! isset($byTs[$ts])) {
                $byTs[$ts] = ['cpu' => null, 'mem' => null, 'disk' => null];
            }

            $byTs[$ts][$short] = round((float) $row->avg, 1);
        }

        $points = [];

        for ($ts = $startBucket; $ts <= $endBucket; $ts += $bucketSize) {
            $slot = $byTs[$ts] ?? ['cpu' => null, 'mem' => null, 'disk' => null];
            $points[] = [
                'at' => CarbonImmutable::createFromTimestamp($ts)->toIso8601String(),
                'cpu' => $slot['cpu'],
                'mem' => $slot['mem'],
                'disk' => $slot['disk'],
            ];
        }

        return [
            'hours' => $hours,
            'bucket_seconds' => $bucketSize,
            'end_at' => CarbonImmutable::createFromTimestamp($endBucket)->toIso8601String(),
            'points' => $points,
        ];
    }

    private function chartBucketSize(int $hours): int
    {
        return match (true) {
            $hours <= 48 => MetricRollup::BUCKET_1M,
            $hours <= 168 => MetricRollup::BUCKET_5M,
            default => MetricRollup::BUCKET_1H,
        };
    }

    private function alignTimestamp(int $timestamp, int $bucketSize): int
    {
        return intdiv($timestamp, $bucketSize) * $bucketSize;
    }
}
