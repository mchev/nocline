<?php

namespace App\Modules\Hosts\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Http\Requests\HostRequest;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\AgentInstallCommand;
use App\Modules\Hosts\Support\HostMetaPresenter;
use App\Modules\Hosts\Support\HostMetricAlerts;
use App\Modules\Metrics\Services\HostMetricAlertEvaluator;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Updates\Services\ReleaseChecker;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HostController extends Controller
{
    public function index(ReleaseChecker $checker, HostMetricsReader $metrics): Response
    {
        $this->authorize('viewAny', Host::class);

        $live = OpsSnapshotUpdated::class;

        return Inertia::render('hosts/Index', [
            'hosts' => Inertia::live(function () use ($checker, $metrics) {
                $hosts = Host::query()->with(['resourcesCheck.state'])->orderBy('name')->limit(2000)->get();
                $series = $metrics->forHosts($hosts);

                return $hosts->map(fn (Host $host) => [
                    'id' => $host->id,
                    'name' => $host->name,
                    'group_id' => $host->group_id,
                    'color' => $host->color,
                    'tags' => $host->tags ?? [],
                    'enrolled' => $host->enrolled_at !== null,
                    'online' => $host->isOnline(),
                    'last_seen_at' => $host->last_seen_at?->toIso8601String(),
                    'agent_version' => $host->agent_version,
                    'agent_outdated' => $checker->agentIsOutdated($host->agent_version),
                    'agent_auto_update' => (bool) $host->agent_auto_update,
                    'metrics' => $series[$host->id] ?? [
                        'cpu' => [],
                        'mem' => [],
                        'disk' => [],
                        'cpu_now' => null,
                        'mem_now' => null,
                        'disk_now' => null,
                    ],
                    ...HostMetaPresenter::forHost($host),
                    ...HostMetricAlerts::presentation($host),
                ])->values();
            }, $live),
            'groups' => Group::options(),
        ]);
    }

    public function show(Host $host, ReleaseChecker $checker, HostMetricsReader $metrics): Response
    {
        $this->authorize('view', $host);

        $host->load(['agentCheck.state', 'resourcesCheck.state', 'linkedChecks.state']);
        $series = $metrics->forHosts(collect([$host]));
        $chartHours = 24;
        $metricsChart = $metrics->chart($host, $chartHours);
        $agent = $host->agentCheck;

        return Inertia::render('hosts/Show', [
            'host' => [
                'id' => $host->id,
                'name' => $host->name,
                'group_id' => $host->group_id,
                'tags' => $host->tags ?? [],
                'color' => $host->color,
                'enrolled' => $host->enrolled_at !== null,
                'online' => $host->isOnline(),
                'last_seen_at' => $host->last_seen_at?->toIso8601String(),
                'agent_version' => $host->agent_version,
                'agent_outdated' => $checker->agentIsOutdated($host->agent_version),
                'agent_auto_update' => (bool) $host->agent_auto_update,
                'hub_version' => (string) config('phare.version'),
                'metrics' => $series[$host->id] ?? [
                    'cpu' => [],
                    'mem' => [],
                    'disk' => [],
                    'cpu_now' => null,
                    'mem_now' => null,
                    'disk_now' => null,
                ],
                'agent_check' => $agent === null ? null : [
                    'id' => $agent->id,
                    'status' => $agent->state?->status->value ?? 'unknown',
                    'interval_sec' => $agent->interval_sec,
                    'timeout_sec' => $agent->timeout_sec,
                    'failure_threshold' => $agent->failure_threshold,
                ],
                ...HostMetaPresenter::forHost($host),
                ...HostMetricAlerts::presentation($host),
            ],
            'linked_checks' => $host->linkedChecks->map(fn (Check $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
                'target' => $c->target,
                'status' => $c->state?->status->value ?? 'unknown',
                'enabled' => $c->enabled,
            ])->values(),
            'attachable_checks' => Check::query()
                ->whereNotIn('type', [CheckType::Agent, CheckType::HostResources])
                ->whereNull('host_id')
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'type'])
                ->map(fn (Check $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type->value,
                ])->values(),
            'groups' => Group::options(),
            'metrics_chart' => $metricsChart,
        ]);
    }

    public function metricsChart(Host $host, Request $request, HostMetricsReader $metrics): JsonResponse
    {
        $this->authorize('view', $host);

        $data = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'end' => ['nullable', 'date'],
        ]);

        $hours = (int) ($data['hours'] ?? 24);
        $end = isset($data['end']) ? CarbonImmutable::parse($data['end']) : null;

        return response()->json($metrics->chart($host, $hours, $end));
    }

    public function store(HostRequest $request): RedirectResponse
    {
        $this->authorize('create', Host::class);

        $host = Host::create(collect($request->validated())->only(['name', 'group_id', 'tags', 'color'])->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Host created. Copy the enrollment command below.')]);

        return back()->with('enrollment', [
            'host' => $host->name,
            'token' => $host->issueEnrollmentToken(),
        ]);
    }

    public function update(HostRequest $request, Host $host, HostMetricAlertEvaluator $metricAlerts): RedirectResponse
    {
        $this->authorize('update', $host);

        $data = $request->validated();
        $monitoring = array_intersect_key($data, array_flip(['interval_sec', 'timeout_sec', 'failure_threshold']));

        if (array_key_exists('metric_alerts', $data)) {
            $data['metric_alerts'] = HostMetricAlerts::resolve($data['metric_alerts']);
        }

        $host->update(collect($data)->only(['name', 'group_id', 'tags', 'color', 'agent_auto_update', 'metric_alerts'])->all());

        if ($monitoring !== []) {
            $host->agentCheck()->update($monitoring);
        }

        if ($host->enrolled_at !== null) {
            $metricAlerts->evaluate($host->refresh());
        }

        $otherFields = ['group_id', 'tags', 'color', 'interval_sec', 'timeout_sec', 'failure_threshold'];
        $agentAutoUpdateOnly = array_key_exists('agent_auto_update', $data)
            && array_all($otherFields, fn (string $key): bool => ! array_key_exists($key, $data));
        $metricAlertsOnly = array_key_exists('metric_alerts', $data)
            && array_all($otherFields, fn (string $key): bool => ! array_key_exists($key, $data))
            && ! array_key_exists('agent_auto_update', $data);

        if (! $agentAutoUpdateOnly && ! $metricAlertsOnly) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Host updated.')]);
        }

        return back();
    }

    public function attachCheck(Request $request, Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $data = $request->validate([
            'check_id' => ['required', 'integer', 'exists:checks,id'],
        ]);

        $check = Check::query()->whereKey($data['check_id'])->firstOrFail();
        abort_if(in_array($check->type, [CheckType::Agent, CheckType::HostResources], true), 422);
        abort_if($check->host_id !== null && $check->host_id !== $host->id, 422);

        $this->authorize('update', $check);
        $check->update(['host_id' => $host->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Monitor linked to this server.')]);

        return back();
    }

    public function detachCheck(Host $host, Check $check): RedirectResponse
    {
        $this->authorize('update', $host);
        abort_if($check->host_id !== $host->id || in_array($check->type, [CheckType::Agent, CheckType::HostResources], true), 404);

        $this->authorize('update', $check);
        $check->update(['host_id' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Monitor unlinked from this server.')]);

        return back();
    }

    public function enrollmentToken(Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        return back()->with('enrollment', [
            'host' => $host->name,
            'token' => $host->issueEnrollmentToken(),
        ]);
    }

    /**
     * Fresh one-line install command to refresh the agent binary (re-runs hub install script).
     */
    public function agentUpdateCommand(Host $host): JsonResponse
    {
        $this->authorize('update', $host);

        abort_unless($host->enrolled_at !== null, 422, __('This server is not connected yet. Use Install agent first.'));

        return response()->json([
            'command' => AgentInstallCommand::curlUpdateSh(),
            'hub_version' => (string) config('phare.version'),
        ]);
    }

    public function revoke(Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $host->revokeApiToken();

        return back();
    }

    public function destroy(Host $host): RedirectResponse
    {
        $this->authorize('delete', $host);

        $host->delete();

        return back();
    }
}
