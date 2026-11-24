<?php

namespace App\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckState;
use App\Modules\Hosts\Models\Host;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Probes\Models\Probe;
use App\Modules\Renewals\Models\Renewal;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** @var array{problems: list<array<string, mixed>>, maintenance: list<array<string, mixed>>}|null */
    private ?array $problemsCache = null;

    /** @var Collection<int, Host>|null */
    private ?Collection $enrolledHostsCache = null;

    public function __invoke(MaintenanceChecker $maintenance, HostMetricsReader $metrics): Response
    {
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
            'overview' => Inertia::live(fn () => $this->overview($maintenance, $metrics), $live),
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
            ->whereHas('state', fn ($q) => $q->where('status', CheckStatus::Down->value))
            ->get();
        $inMaintenance = array_flip($maintenance->activeCheckIds($down));
        [$underMaintenance, $problems] = $down->partition(fn (Check $c) => isset($inMaintenance[$c->id]));

        return $this->problemsCache = [
            'problems' => $problems->take(20)
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'group' => $c->group?->name,
                    'since_at' => $c->state?->since_at?->toIso8601String(),
                    'error' => $c->state?->last_error,
                ])->values()->all(),
            'maintenance' => $underMaintenance->take(20)
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'group' => $c->group?->name,
                ])->values()->all(),
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
     *     heartbeats: list<array<string, mixed>>,
     *     renewals: list<array<string, mixed>>
     * }
     */
    private function overview(MaintenanceChecker $maintenance, HostMetricsReader $metrics): array
    {
        $sites = Check::query()
            ->with('state')
            ->where('category', CheckCategory::Website)
            ->orderBy('name')
            ->limit(40)
            ->get();
        $siteMaint = array_flip($maintenance->activeCheckIds($sites));

        $hosts = $this->enrolledHosts();
        $series = $metrics->forHosts($hosts, 24);

        $heartbeats = Check::query()
            ->with('state')
            ->where('type', CheckType::Heartbeat)
            ->orderBy('name')
            ->limit(40)
            ->get();
        $hbMaint = array_flip($maintenance->activeCheckIds($heartbeats));

        $renewals = Renewal::query()
            ->where('expires_on', '<=', now()->addDays(30))
            ->orderBy('expires_on')
            ->limit(20)
            ->get(['id', 'label', 'kind', 'expires_on']);

        $siteRows = $sites->map(fn (Check $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'target' => $c->target,
            'status' => $c->state?->status->value ?? 'unknown',
            'latency_ms' => $c->state?->last_latency_ms,
            'in_maintenance' => isset($siteMaint[$c->id]),
            'enabled' => $c->enabled,
        ])->sortBy([
            fn (array $row) => $this->checkProblemRank($row['enabled'], $row['in_maintenance'], $row['status']),
            fn (array $row) => $row['name'],
        ])->values()->all();

        $serverRows = $hosts->map(function (Host $h) use ($series) {
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
                'metrics' => $m,
            ];
        })->sortBy([
            fn (array $row) => $row['online'] ? 1 : 0,
            fn (array $row) => -max(
                (float) ($row['metrics']['cpu_now'] ?? 0),
                (float) ($row['metrics']['mem_now'] ?? 0),
                (float) ($row['metrics']['disk_now'] ?? 0),
            ),
            fn (array $row) => $row['name'],
        ])->values()->all();

        $heartbeatRows = $heartbeats->map(fn (Check $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->state?->status->value ?? 'unknown',
            'since_at' => $c->state?->since_at?->toIso8601String(),
            'in_maintenance' => isset($hbMaint[$c->id]),
            'enabled' => $c->enabled,
            'grace_sec' => $c->heartbeatGraceSeconds(),
        ])->sortBy([
            fn (array $row) => $this->checkProblemRank($row['enabled'], $row['in_maintenance'], $row['status']),
            fn (array $row) => $row['name'],
        ])->values()->all();

        return [
            'sites' => $siteRows,
            'servers' => $serverRows,
            'heartbeats' => $heartbeatRows,
            'renewals' => $renewals->map(fn (Renewal $r) => [
                'id' => $r->id,
                'name' => $r->label,
                'kind' => $r->kind,
                'expires_on' => $r->expires_on?->toDateString(),
            ])->values()->all(),
        ];
    }

    private function checkProblemRank(bool $enabled, bool $inMaintenance, string $status): int
    {
        if (! $enabled) {
            return 4;
        }

        if ($inMaintenance) {
            return 3;
        }

        return match ($status) {
            CheckStatus::Down->value => 0,
            CheckStatus::Unknown->value => 1,
            default => 2,
        };
    }
}
