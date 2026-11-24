<?php

namespace App\Modules\Wallboard\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\SiteHealthCompanion;
use App\Modules\Checks\Support\WebsiteMonitorRows;
use App\Modules\Hosts\Models\Host;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the single aggregated payload read by the wallboard (cached, read-only).
 */
class WallboardAggregator
{
    public const CACHE_TTL_SECONDS = 10;

    public function __construct(
        private MaintenanceChecker $maintenance,
        private WebsiteMonitorRows $websiteRows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Wallboard $wallboard): array
    {
        return Cache::remember(
            "wallboard:{$wallboard->id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($wallboard),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Wallboard $wallboard): array
    {
        $groupIds = $wallboard->group?->selfAndDescendantIds();

        $checks = Check::query()
            ->where('enabled', true)
            ->when($groupIds, fn ($q) => $q->whereIn('group_id', $groupIds))
            ->with('state')
            ->get();

        $status = fn (Check $c): CheckStatus => $c->state ? $c->state->status : CheckStatus::Unknown;
        $inMaintenance = array_flip($this->maintenance->activeCheckIds($checks));
        $failing = $checks->filter(fn ($c) => $status($c) === CheckStatus::Down);
        $underMaintenance = $failing->filter(fn ($c) => isset($inMaintenance[$c->id]));
        $failing = $failing->reject(fn ($c) => isset($inMaintenance[$c->id]));

        $hosts = Host::query()
            ->whereNotNull('enrolled_at')
            ->when($groupIds, fn ($q) => $q->whereIn('group_id', $groupIds))
            ->orderBy('name')
            ->get();

        $metrics = $this->recentMetrics($hosts->pluck('id')->all());
        $checkIds = $checks->pluck('id');

        $sites = Check::query()
            ->where('enabled', true)
            ->where('category', CheckCategory::Website)
            ->when($groupIds, fn ($q) => $q->whereIn('group_id', $groupIds))
            ->with('state')
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->reject(fn (Check $c) => SiteHealthCompanion::looksLikeHealthCompanion($c))
            ->values();

        return [
            'name' => $wallboard->name,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'up' => $checks->filter(fn ($c) => $status($c) === CheckStatus::Up)->count(),
                'down' => $failing->count(),
                'maintenance' => $underMaintenance->count(),
                'unknown' => $checks->filter(fn ($c) => $status($c) === CheckStatus::Unknown)->count(),
                'hosts_online' => $hosts->filter->isOnline()->count(),
                'hosts_total' => $hosts->count(),
            ],
            'active_incidents' => Incident::query()->open()->whereIn('check_id', $checkIds)
                ->with('check:id,name')->latest('started_at')->limit(20)->get()
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'since' => $i->started_at->toIso8601String(),
                    'acknowledged' => $i->isAcknowledged(),
                ])->all(),
            'hosts' => $hosts->map(fn (Host $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'online' => $h->isOnline(),
                'cpu_avg_5m' => $metrics[$h->id]['cpu.usage_pct'] ?? null,
                'mem_pct' => $metrics[$h->id]['mem.used_pct'] ?? null,
                'disk_pct' => $metrics[$h->id]['disk.used_pct'] ?? null,
            ])->all(),
            'checks_failing' => $failing
                ->sortBy(fn ($c) => $c->state->since_at)->take(30)
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'since' => $c->state->since_at?->toIso8601String(),
                    'latency_ms' => $c->state->last_latency_ms,
                    'error' => $c->state->last_error,
                ])->values()->all(),
            'sites' => $this->websiteRows->rows($sites),
        ];
    }

    /**
     * @param  array<int, int>  $hostIds
     * @return array<int, array<string, float>>
     */
    private function recentMetrics(array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        $rows = MetricRollup::query()
            ->selectRaw('host_id, metric_key, avg(avg) as value')
            ->whereIn('host_id', $hostIds)
            ->where('bucket_size', MetricRollup::BUCKET_1M)
            ->where('bucket_start', '>=', now()->subMinutes(5))
            ->whereIn('metric_key', ['cpu.usage_pct', 'mem.used_pct', 'disk.used_pct'])
            ->groupBy('host_id', 'metric_key')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->host_id][$row->metric_key] = round((float) $row->getAttribute('value'), 1);
        }

        return $out;
    }
}
