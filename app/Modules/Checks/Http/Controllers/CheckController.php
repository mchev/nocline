<?php

namespace App\Modules\Checks\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Http\Requests\CheckRequest;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckLatencySeries;
use App\Modules\Checks\Services\CheckStatusHistory;
use App\Modules\Checks\Services\KumaImporter;
use App\Modules\Checks\Support\CheckFaviconResponse;
use App\Modules\Checks\Support\HistoryWindow;
use App\Modules\Checks\Support\SiteFrameworkTag;
use App\Modules\Checks\Support\SiteHealthCompanion;
use App\Modules\Checks\Support\WebsiteSslStatus;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Renewals\Models\Renewal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CheckController extends Controller
{
    public function index(
        MaintenanceChecker $maintenance,
        HostMetricsReader $metrics,
        CheckStatusHistory $history,
    ): Response {
        $this->authorize('viewAny', Check::class);

        $live = OpsSnapshotUpdated::class;

        return Inertia::render('checks/Index', [
            'checks' => Inertia::live(function () use ($maintenance, $metrics, $history) {
                $checks = Check::query()
                    ->whereNotIn('type', [CheckType::Agent, CheckType::HostResources])
                    ->with(['state', 'host:id,name,enrolled_at,last_seen_at'])
                    ->orderBy('name')
                    ->limit(2000)
                    ->get();
                $inMaintenance = array_flip($maintenance->activeCheckIds($checks));
                $linkedHosts = $checks->pluck('host')->filter()->unique('id')->values();
                $series = $metrics->forHosts($linkedHosts, 24);
                $histories = $history->forChecks($checks, $maintenance, null, HistoryWindow::dashboard());
                $sslRenewals = Renewal::query()
                    ->where('kind', 'ssl')
                    ->whereIn('check_id', $checks->pluck('id'))
                    ->get()
                    ->keyBy('check_id');

                return $checks->map(function (Check $c) use ($inMaintenance, $series, $histories, $sslRenewals) {
                    $ssl = $sslRenewals->get($c->id);

                    return [
                        'id' => $c->id,
                        ...SiteHealthCompanion::linkMeta($c),
                        'name' => $c->name,
                        'type' => $c->type->value,
                        'category' => $c->category->value,
                        'tags' => $c->tags ?? [],
                        'target' => $c->target,
                        'interval_sec' => $c->interval_sec,
                        'timeout_sec' => $c->timeout_sec,
                        'failure_threshold' => $c->failure_threshold,
                        'execution' => $c->execution->value,
                        'quorum' => $c->quorum,
                        'enabled' => $c->enabled,
                        'group_id' => $c->group_id,
                        'host_id' => $c->host_id,
                        'host_name' => $c->host_id && $c->type !== CheckType::Agent ? $c->host?->name : null,
                        'host_online' => $c->type === CheckType::Agent ? $c->host?->isOnline() : null,
                        'status' => $c->state?->status->value ?? 'unknown',
                        'since_at' => $c->state?->since_at?->toIso8601String(),
                        'latency_ms' => $c->state?->last_latency_ms,
                        'last_error' => $c->state?->last_error,
                        'in_maintenance' => isset($inMaintenance[$c->id]),
                        'metrics' => $c->type === CheckType::Agent && $c->host_id
                            ? ($series[$c->host_id] ?? null)
                            : null,
                        'history' => $histories[$c->id]['history'] ?? [],
                        'timeline' => $histories[$c->id]['timeline'] ?? [],
                        'uptime_24h_pct' => $histories[$c->id]['uptime_24h_pct'] ?? null,
                        'favicon_url' => $c->faviconUrl(),
                        'ssl_status' => WebsiteSslStatus::forCheck($c, $ssl),
                        'ssl_days_remaining' => $ssl !== null && $ssl->check_error === null ? $ssl->daysRemaining() : null,
                        'site_framework' => SiteFrameworkTag::fromCheck($c)?->value,
                    ];
                })->values();
            }, $live),
            'groups' => Group::options(),
            'hosts' => Host::query()->orderBy('name')->get(['id', 'name']),
            'types' => array_column(CheckType::cases(), 'value'),
            'categories' => array_column(CheckCategory::cases(), 'value'),
        ]);
    }

    public function show(
        Check $check,
        MaintenanceChecker $maintenance,
        CheckStatusHistory $history,
        CheckLatencySeries $latencySeries,
        Request $request,
    ): Response {
        $this->authorize('view', $check);

        $check->load(['state', 'host:id,name,enrolled_at,last_seen_at']);
        $chartHours = max(1, min(168, (int) $request->query('chart_hours', 3)));
        $recent = $history->forChecks(
            new EloquentCollection([$check]),
            $maintenance,
            null,
            HistoryWindow::dashboard(),
        )[$check->id] ?? ['history' => [], 'timeline' => [], 'uptime_24h_pct' => null];

        return Inertia::render('checks/Show', [
            'check' => $this->checkDetailPayload($check, $maintenance),
            'recent' => $recent,
            'stats' => [
                'latency_avg_24h' => $latencySeries->averageLatencyMs($check, 24),
                'uptime_24h' => $latencySeries->uptimePct($check, 24),
                'uptime_30d' => $latencySeries->uptimePct($check, 24 * 30),
                'uptime_365d' => $latencySeries->uptimePct($check, 24 * 365),
            ],
            'renewals' => $this->renewalHints($check),
            'chart' => [
                'hours' => $chartHours,
                'points' => $latencySeries->points($check, $chartHours),
                'down_intervals' => $latencySeries->downIntervals($check, $chartHours),
            ],
            'groups' => Group::options(),
            'hosts' => Host::query()->orderBy('name')->get(['id', 'name']),
            'types' => array_column(CheckType::cases(), 'value'),
            'categories' => array_column(CheckCategory::cases(), 'value'),
        ]);
    }

    public function chart(
        Check $check,
        Request $request,
        CheckLatencySeries $latencySeries,
    ): JsonResponse {
        $this->authorize('view', $check);

        $hours = max(1, min(168, (int) $request->query('hours', 3)));

        return response()->json([
            'hours' => $hours,
            'points' => $latencySeries->points($check, $hours),
            'down_intervals' => $latencySeries->downIntervals($check, $hours),
        ]);
    }

    public function history(
        Check $check,
        Request $request,
        MaintenanceChecker $maintenance,
        CheckStatusHistory $history,
    ): JsonResponse {
        $this->authorize('view', $check);

        $data = $request->validate([
            'end' => ['nullable', 'date'],
            'hours' => ['nullable', 'integer', 'min:24', 'max:720'],
        ]);

        $hours = (int) ($data['hours'] ?? 168);
        $buckets = min($hours, 336);
        $window = HistoryWindow::detailPage($hours, $buckets);
        $end = isset($data['end'])
            ? CarbonImmutable::parse($data['end'])
            : null;

        return response()->json(
            $this->historySlice($check, $maintenance, $history, $end, $window),
        );
    }

    public function store(CheckRequest $request): RedirectResponse
    {
        $this->authorize('create', Check::class);

        Check::create($request->checkData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check created.')]);

        return back();
    }

    public function update(CheckRequest $request, Check $check): RedirectResponse
    {
        $this->authorize('update', $check);

        $data = $request->checkData();

        if (array_key_exists('config', $data)) {
            $data['config'] = array_merge($check->config ?? [], array_filter($data['config'], fn ($v) => $v !== null && $v !== ''));
        }

        $check->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check updated.')]);

        return back();
    }

    public function snooze(Request $request, Check $check): RedirectResponse
    {
        $this->authorize('update', $check);

        $data = $request->validate([
            'hours' => ['required', 'integer', Rule::in([1, 4, 8, 24, 72])],
        ]);

        $check->update(['alerts_snoozed_until' => now()->addHours($data['hours'])]);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Alerts snoozed for :hours h.', ['hours' => $data['hours']]),
        ]);

        return back();
    }

    public function unsnooze(Check $check): RedirectResponse
    {
        $this->authorize('update', $check);

        $check->update(['alerts_snoozed_until' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alert snooze cleared.')]);

        return back();
    }

    public function destroy(Check $check): RedirectResponse
    {
        $this->authorize('delete', $check);

        $check->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check deleted.')]);

        return redirect()->route('checks.index');
    }

    /**
     * Preview (dry_run) or run an Uptime Kuma import from an uploaded backup / kuma.db.
     */
    public function importKuma(Request $request, KumaImporter $importer): RedirectResponse
    {
        $this->authorize('create', Check::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'dry_run' => ['boolean'],
        ]);

        try {
            $result = $importer->import($importer->read($data['file']->getRealPath()), (bool) ($data['dry_run'] ?? false));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => __($e->getMessage())]);
        }

        if (! ($data['dry_run'] ?? false)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':count monitors imported.', ['count' => count($result['created'])])]);
        }

        return back()->with('kuma_import', $result + ['dry_run' => (bool) ($data['dry_run'] ?? false)]);
    }

    /**
     * Apply one action to several checks at once (organise, pause, delete).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:checks,id'],
            'action' => ['required', Rule::in(['assign', 'enable', 'disable', 'delete'])],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'category' => ['nullable', Rule::enum(CheckCategory::class)],
            'add_tags' => ['nullable', 'array', 'max:20'],
            'add_tags.*' => ['string', 'max:50'],
        ]);

        $checks = Check::query()->whereKey($data['ids'])->get();

        foreach ($checks as $check) {
            $this->authorize($data['action'] === 'delete' ? 'delete' : 'update', $check);
        }

        foreach ($checks as $check) {
            match ($data['action']) {
                'enable' => $check->update(['enabled' => true]),
                'disable' => $check->update(['enabled' => false]),
                'delete' => $check->delete(),
                'assign' => $check->update(array_filter([
                    'group_id' => $request->has('group_id') ? $data['group_id'] : $check->group_id,
                    'category' => $data['category'] ?? $check->category->value,
                    'tags' => array_values(array_unique([...($check->tags ?? []), ...($data['add_tags'] ?? [])])),
                ], fn ($v, $k) => $k === 'group_id' || $v !== null, ARRAY_FILTER_USE_BOTH)),
                default => null,
            };
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count checks updated.', ['count' => $checks->count()])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDetailPayload(Check $c, MaintenanceChecker $maintenance): array
    {
        $inMaintenance = $maintenance->activeCheckIds(collect([$c]));
        $ssl = Renewal::query()
            ->where('kind', 'ssl')
            ->where('check_id', $c->id)
            ->first();

        return [
            'id' => $c->id,
            ...SiteHealthCompanion::linkMeta($c),
            'name' => $c->name,
            'type' => $c->type->value,
            'category' => $c->category->value,
            'tags' => $c->tags ?? [],
            'target' => $c->target,
            'interval_sec' => $c->interval_sec,
            'timeout_sec' => $c->timeout_sec,
            'failure_threshold' => $c->failure_threshold,
            'execution' => $c->execution->value,
            'quorum' => $c->quorum,
            'enabled' => $c->enabled,
            'group_id' => $c->group_id,
            'host_id' => $c->host_id,
            'host_name' => $c->host_id && $c->type !== CheckType::Agent ? $c->host?->name : null,
            'status' => $c->state?->status->value ?? 'unknown',
            'since_at' => $c->state?->since_at?->toIso8601String(),
            'latency_ms' => $c->state?->last_latency_ms,
            'last_error' => $c->state?->last_error,
            'in_maintenance' => in_array($c->id, $inMaintenance, true),
            'favicon_url' => $c->faviconUrl(),
            'ssl_status' => WebsiteSslStatus::forCheck($c, $ssl),
            'ssl_days_remaining' => $ssl !== null && $ssl->check_error === null ? $ssl->daysRemaining() : null,
            'site_framework' => SiteFrameworkTag::fromCheck($c)?->value,
            'created_at' => $c->created_at?->toIso8601String(),
            'config' => $c->config ?? [],
            'alerts_snoozed_until' => $c->alerts_snoozed_until?->toIso8601String(),
        ];
    }

    /**
     * @return array{ssl: ?array{expires_on: string, days: int|null}, domain: ?array{label: string, expires_on: string, days: int|null}}
     */
    private function renewalHints(Check $check): array
    {
        $ssl = Renewal::query()
            ->where('kind', 'ssl')
            ->where('check_id', $check->id)
            ->first();

        $host = $check->target !== ''
            ? parse_url($check->target, PHP_URL_HOST)
            : null;
        $domain = $host
            ? Renewal::query()->where('kind', 'domain')->where('label', $host)->first()
            : null;

        return [
            'ssl' => $ssl === null ? null : [
                'expires_on' => $ssl->expires_on->toDateString(),
                'days' => $ssl->check_error === null ? $ssl->daysRemaining() : null,
            ],
            'domain' => $domain === null ? null : [
                'label' => $domain->label,
                'expires_on' => $domain->expires_on->toDateString(),
                'days' => $domain->daysRemaining(),
            ],
        ];
    }

    /**
     * @return array{
     *     history: list<string>,
     *     timeline: list<array{s: string, at: string, error: ?string}>,
     *     uptime_pct: float|null,
     *     window_start: string,
     *     window_end: string,
     *     hours: float,
     *     can_load_earlier: bool
     * }
     */
    private function historySlice(
        Check $check,
        MaintenanceChecker $maintenance,
        CheckStatusHistory $history,
        ?CarbonImmutable $endAt,
        HistoryWindow $window,
    ): array {
        $end = CarbonImmutable::instance($endAt ?? now());
        $hist = $history->forChecks(new EloquentCollection([$check]), $maintenance, $end, $window)[$check->id]
            ?? ['history' => [], 'timeline' => [], 'uptime_24h_pct' => null];

        $windowStart = $end->subSeconds((int) round($window->hours * 3600));
        $created = $check->created_at !== null
            ? CarbonImmutable::instance($check->created_at)
            : $windowStart;

        return [
            'history' => $hist['history'],
            'timeline' => $hist['timeline'],
            'uptime_pct' => $hist['uptime_24h_pct'],
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $end->toIso8601String(),
            'hours' => $window->hours,
            'can_load_earlier' => $created < $windowStart,
        ];
    }

    public function favicon(Check $check, CheckFaviconResponse $favicons): BinaryFileResponse
    {
        $this->authorize('view', $check);

        return $favicons->file($check);
    }
}
