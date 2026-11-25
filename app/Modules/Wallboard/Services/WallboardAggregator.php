<?php

namespace App\Modules\Wallboard\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\SiteHealthCompanion;
use App\Modules\Checks\Support\WebsiteMonitorRows;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetricAlerts;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the single aggregated payload read by the wallboard (cached, read-only).
 */
class WallboardAggregator
{
    public const CACHE_TTL_SECONDS = 10;

    public const HOST_SPARKLINE_POINTS = 24;

    public function __construct(
        private MaintenanceChecker $maintenance,
        private WebsiteMonitorRows $websiteRows,
        private HostMetricsReader $hostMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Wallboard $wallboard, string $wallboardToken): array
    {
        $data = Cache::remember(
            "wallboard:{$wallboard->id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($wallboard),
        );

        return [
            ...$data,
            'sites' => array_map(function (array $site) use ($wallboardToken) {
                if ($site['favicon_url'] !== null) {
                    $site['favicon_url'] = route('wallboard.favicon', [
                        'token' => $wallboardToken,
                        'check' => $site['id'],
                    ]);
                }

                return $site;
            }, $data['sites']),
        ];
    }

    /**
     * Website tiles shown on this wallboard (same filter as the payload).
     *
     * @return Collection<int, Check>
     */
    public function visibleSites(Wallboard $wallboard): EloquentCollection
    {
        $groupIds = $wallboard->group?->selfAndDescendantIds();

        return Check::query()
            ->where('enabled', true)
            ->where('category', CheckCategory::Website)
            ->when($groupIds, fn ($q) => $q->whereIn('group_id', $groupIds))
            ->with('state')
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->reject(fn (Check $c) => SiteHealthCompanion::looksLikeHealthCompanion($c))
            ->values();
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
            ->with(['resourcesCheck.state'])
            ->whereNotNull('enrolled_at')
            ->when($groupIds, fn ($q) => $q->whereIn('group_id', $groupIds))
            ->orderBy('name')
            ->get();

        $hostIds = $hosts->pluck('id')->all();
        $metricSeries = $this->hostMetrics->forHosts($hosts, self::HOST_SPARKLINE_POINTS);
        $checkIds = $checks->pluck('id');

        $sites = $this->visibleSites($wallboard);

        return [
            'name' => $wallboard->name,
            'summary' => [
                'down' => $failing->count(),
                'maintenance' => $underMaintenance->count(),
            ],
            'active_incidents' => Incident::query()->open()->whereIn('check_id', $checkIds)
                ->with('check:id,name')->latest('started_at')->limit(20)->get()
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'since' => $i->started_at->toIso8601String(),
                    'acknowledged' => $i->isAcknowledged(),
                ])->all(),
            'hosts' => $hosts->map(function (Host $h) use ($metricSeries) {
                return [
                    'id' => $h->id,
                    'name' => $h->name,
                    'online' => $h->isOnline(),
                    ...HostMetricAlerts::presentation($h),
                    'metrics' => $metricSeries[$h->id] ?? [
                        'cpu' => [],
                        'mem' => [],
                        'disk' => [],
                        'cpu_now' => null,
                        'mem_now' => null,
                        'disk_now' => null,
                        'disk_used_bytes' => null,
                        'disk_total_bytes' => null,
                    ],
                ];
            })->all(),
            'sites' => $this->websiteRows->rows($sites),
        ];
    }
}
