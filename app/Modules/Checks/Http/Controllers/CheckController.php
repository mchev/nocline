<?php

namespace App\Modules\Checks\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Http\Requests\CheckRequest;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckStatusHistory;
use App\Modules\Checks\Services\KumaImporter;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Metrics\Services\HostMetricsReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

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
                    ->with(['state', 'host:id,name,enrolled_at,last_seen_at'])
                    ->orderBy('name')
                    ->limit(2000)
                    ->get();
                $inMaintenance = array_flip($maintenance->activeCheckIds($checks));
                $linkedHosts = $checks->pluck('host')->filter()->unique('id')->values();
                $series = $metrics->forHosts($linkedHosts, 24);
                $histories = $history->forChecks($checks, $maintenance);

                return $checks->map(fn (Check $c) => [
                    'id' => $c->id,
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
                    'host_online' => $c->host?->isOnline(),
                    'status' => $c->state?->status->value ?? 'unknown',
                    'since_at' => $c->state?->since_at?->toIso8601String(),
                    'latency_ms' => $c->state?->last_latency_ms,
                    'last_error' => $c->state?->last_error,
                    'in_maintenance' => isset($inMaintenance[$c->id]),
                    'metrics' => $c->host_id ? ($series[$c->host_id] ?? null) : null,
                    'history' => $histories[$c->id]['history'] ?? [],
                    'uptime_24h_pct' => $histories[$c->id]['uptime_24h_pct'] ?? null,
                ])->values();
            }, $live),
            'groups' => Group::options(),
            'hosts' => Host::query()->orderBy('name')->get(['id', 'name']),
            'types' => array_column(CheckType::cases(), 'value'),
            'categories' => array_column(CheckCategory::cases(), 'value'),
        ]);
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

        $check->update($request->checkData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check updated.')]);

        return back();
    }

    public function destroy(Check $check): RedirectResponse
    {
        $this->authorize('delete', $check);

        $check->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check deleted.')]);

        return back();
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
}
