<?php

namespace App\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckState;
use App\Modules\Checks\Services\CheckStatusHistory;
use App\Modules\Checks\Support\HistoryWindow;
use App\Modules\Checks\Support\SiteHealthCompanion;
use App\Modules\Checks\Support\WebsiteMonitorRows;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetaPresenter;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Probes\Models\Probe;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Updates\Services\ReleaseChecker;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const OVERVIEW_WEBSITE_LIMIT = 40;

    /** @var array{problems: list<array<string, mixed>>, maintenance: list<array<string, mixed>>}|null */
    private ?array $problemsCache = null;

    /** @var Collection<int, Host>|null */
    private ?Collection $enrolledHostsCache = null;

    public function __invoke(
        MaintenanceChecker $maintenance,
        HostMetricsReader $metrics,
        CheckStatusHistory $history,
    ): Response {
        $live = OpsSnapshotUpdated::class;

        return Inertia::render('Dashboard', [
            'checks' => Inertia::live(fn () => $this->checkCounts($maintenance), $live),
            'monitors_total' => Inertia::live(fn () => Check::query()->count(), $live),
            'channels_count' => Inertia::live(
                fn () => NotificationChannel::query()->where('enabled', true)->count(),
                $live,
            ),
            'problems' => Inertia::live(fn () => $this->problems($maintenance)['problems'], $live),
            'maintenance' => Inertia::live(fn () => $this->problems($maintenance)['maintenance'], $live),
            'incidents_open' => Inertia::live(fn () => Incident::query()->open()->count(), $live),
            'incidents_unacknowledged' => Inertia::live(
                fn () => Incident::query()->open()->whereNull('acknowledged_at')->count(),
                $live,
            ),
            'hosts_online' => Inertia::live(fn () => $this->enrolledHosts()->filter->isOnline()->count(), $live),
            'hosts_total' => Inertia::live(fn () => $this->enrolledHosts()->count(), $live),
            'probes_online' => Inertia::live(
                fn () => Probe::query()->where('enabled', true)->get()->filter->isOnline()->count(),
                $live,
            ),
            'renewals_soon' => Inertia::live(
                fn () => Renewal::query()->where('expires_on', '<=', now()->addDays(30))->count(),
                $live,
            ),
            'overview' => Inertia::live(fn () => $this->overview($maintenance, $metrics, $history, app(ReleaseChecker::class)), $live),
        ]);
    }

    /**
     * @return array{up: int, down: int, maintenance: int, unknown: int}
     */
    private function checkCounts(MaintenanceChecker $maintenance): array
    {
        $states = CheckState::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $split = $this->problems($maintenance);

        return [
            'up' => (int) ($states[CheckStatus::Up->value] ?? 0),
            'down' => count($split['problems']),
            'maintenance' => count($split['maintenance']),
            'unknown' => (int) ($states[CheckStatus::Unknown->value] ?? 0),
        ];
    }

    /**
     * @return array{problems: list<array<string, mixed>>, maintenance: list<array<string, mixed>>}
     */
    private function problems(MaintenanceChecker $maintenance): array
    {
        if ($this->problemsCache !== null) {
            return $this->problemsCache;
        }

        $down = Check::query()->with(['state', 'group:id,name'])
            ->whereHas('state', fn ($q) => $q->whereIn('status', [
                CheckStatus::Down->value,
                CheckStatus::Degraded->value,
            ]))
            ->get();
        $inMaintenance = array_flip($maintenance->activeCheckIds($down));
        [$underMaintenance, $problems] = $down->partition(fn (Check $c) => isset($inMaintenance[$c->id]));

        return $this->problemsCache = [
            'problems' => array_values($problems->take(20)
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'group' => $c->group?->name,
                    'since_at' => $c->state?->since_at?->toIso8601String(),
                    'error' => $c->state?->last_error,
                    'severity' => $c->state?->status === CheckStatus::Degraded ? 'degraded' : 'down',
                ])->all()),
            'maintenance' => array_values($underMaintenance->take(20)
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'group' => $c->group?->name,
                ])->all()),
        ];
    }

    /**
     * @return Collection<int, Host>
     */
    private function enrolledHosts(): Collection
    {
        return $this->enrolledHostsCache ??= Host::query()->whereNotNull('enrolled_at')->orderBy('name')->get();
    }

    /**
     * @return array{
     *     sites: list<array<string, mixed>>,
     *     servers: list<array<string, mixed>>,
     *     object_storage: list<array<string, mixed>>,
     *     heartbeats: list<array<string, mixed>>,
     *     renewals: list<array<string, mixed>>
     * }
     */
    private function overview(MaintenanceChecker $maintenance, HostMetricsReader $metrics, CheckStatusHistory $history, ReleaseChecker $releaseChecker): array
    {
        $sites = Check::query()
            ->with('state')
            ->where('category', CheckCategory::Website)
            ->get()
            ->reject(fn (Check $c) => SiteHealthCompanion::looksLikeHealthCompanion($c))
            ->values();
        $hosts = $this->enrolledHosts();
        $series = $metrics->forHosts($hosts, 24);

        $objectStorage = Check::query()
            ->with('state')
            ->whereJsonContains('tags', 'object-storage')
            ->orderBy('name')
            ->limit(40)
            ->get();
        $osMaint = array_flip($maintenance->activeCheckIds($objectStorage));

        $heartbeats = Check::query()
            ->with('state')
            ->where('type', CheckType::Heartbeat)
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->reject(fn (Check $c) => in_array('object-storage', $c->tags ?? [], true))
            ->values()
            ->take(40);
        $hbMaint = array_flip($maintenance->activeCheckIds($heartbeats));

        $renewals = Renewal::query()
            ->where('expires_on', '<=', now()->addDays(30))
            ->orderBy('expires_on')
            ->limit(20)
            ->get(['id', 'label', 'kind', 'expires_on']);

        $tracked = $sites->concat($objectStorage)->concat($heartbeats)->unique('id');
        $histories = $history->forChecks($tracked, $maintenance, null, HistoryWindow::dashboard());

        $siteRows = array_slice(
            app(WebsiteMonitorRows::class)->rows($sites),
            0,
            self::OVERVIEW_WEBSITE_LIMIT,
        );

        $serverRows = $hosts->map(function (Host $h) use ($series, $releaseChecker) {
            $m = $series[$h->id] ?? [
                'cpu' => [], 'mem' => [], 'disk' => [],
                'cpu_now' => null, 'mem_now' => null, 'disk_now' => null,
            ];

            return [
                'id' => $h->id,
                'name' => $h->name,
                'online' => $h->isOnline(),
                'last_seen_at' => $h->last_seen_at?->toIso8601String(),
                'agent_version' => $h->agent_version,
                'agent_outdated' => $releaseChecker->agentIsOutdated($h->agent_version),
                'metrics' => $m,
                ...HostMetaPresenter::forHost($h),
            ];
        })
            ->sortBy(fn (array $row) => $row['name'])
            ->sortBy(fn (array $row) => -max(
                (float) ($row['metrics']['cpu_now'] ?? 0),
                (float) ($row['metrics']['mem_now'] ?? 0),
                (float) ($row['metrics']['disk_now'] ?? 0),
            ))
            ->sortBy(fn (array $row) => $row['online'] ? 1 : 0)
            ->values()
            ->all();

        $serverRows = array_values($serverRows);

        $objectStorageRows = $objectStorage->map(fn (Check $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'target' => $c->target,
            'status' => $c->state?->status->value ?? 'unknown',
            'since_at' => $c->state?->since_at?->toIso8601String(),
            'latency_ms' => $c->state?->last_latency_ms,
            'in_maintenance' => isset($osMaint[$c->id]),
            'enabled' => $c->enabled,
            'grace_sec' => $c->type === CheckType::Heartbeat ? $c->heartbeatGraceSeconds() : null,
            'history' => $histories[$c->id]['history'] ?? [],
            'timeline' => $histories[$c->id]['timeline'] ?? [],
            'uptime_24h_pct' => $histories[$c->id]['uptime_24h_pct'] ?? null,
        ])
            ->sortBy(fn (array $row) => $row['name'])
            ->sortBy(fn (array $row) => $this->checkProblemRank($row['enabled'], $row['in_maintenance'], $row['status']))
            ->values()
            ->all();

        $objectStorageRows = array_values($objectStorageRows);

        $heartbeatRows = $heartbeats->map(fn (Check $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->state?->status->value ?? 'unknown',
            'since_at' => $c->state?->since_at?->toIso8601String(),
            'in_maintenance' => isset($hbMaint[$c->id]),
            'enabled' => $c->enabled,
            'grace_sec' => $c->heartbeatGraceSeconds(),
            'history' => $histories[$c->id]['history'] ?? [],
            'timeline' => $histories[$c->id]['timeline'] ?? [],
            'uptime_24h_pct' => $histories[$c->id]['uptime_24h_pct'] ?? null,
        ])
            ->sortBy(fn (array $row) => $row['name'])
            ->sortBy(fn (array $row) => $this->checkProblemRank($row['enabled'], $row['in_maintenance'], $row['status']))
            ->values()
            ->all();

        $heartbeatRows = array_values($heartbeatRows);

        return [
            'sites' => $siteRows,
            'servers' => $serverRows,
            'object_storage' => $objectStorageRows,
            'heartbeats' => $heartbeatRows,
            'renewals' => array_values($renewals->map(fn (Renewal $r) => [
                'id' => $r->id,
                'name' => $r->label,
                'kind' => $r->kind,
                'expires_on' => $r->expires_on->toDateString(),
            ])->all()),
        ];
    }

    private function checkProblemRank(bool $enabled, bool $inMaintenance, string $status): int
    {
        return WebsiteMonitorRows::problemRank($enabled, $inMaintenance, $status);
    }
}
