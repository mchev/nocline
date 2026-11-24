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
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Updates\Services\ReleaseChecker;
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
                $hosts = Host::query()->orderBy('name')->limit(2000)->get();
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
                    'metrics' => $series[$host->id] ?? [
                        'cpu' => [],
                        'mem' => [],
                        'disk' => [],
                        'cpu_now' => null,
                        'mem_now' => null,
                        'disk_now' => null,
                    ],
                    ...HostMetaPresenter::forHost($host),
                ])->values();
            }, $live),
            'groups' => Group::options(),
        ]);
    }

    public function show(Host $host, ReleaseChecker $checker, HostMetricsReader $metrics): Response
    {
        $this->authorize('view', $host);

        $host->load(['agentCheck.state', 'linkedChecks.state']);
        $series = $metrics->forHosts(collect([$host]));
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
                ->where('type', '!=', CheckType::Agent)
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
        ]);
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

    public function update(HostRequest $request, Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $data = $request->validated();
        $monitoring = array_intersect_key($data, array_flip(['interval_sec', 'timeout_sec', 'failure_threshold']));

        $host->update(collect($data)->only(['name', 'group_id', 'tags', 'color'])->all());

        if ($monitoring !== []) {
            $host->agentCheck()->update($monitoring);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Host updated.')]);

        return back();
    }

    public function attachCheck(Request $request, Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $data = $request->validate([
            'check_id' => ['required', 'integer', 'exists:checks,id'],
        ]);

        $check = Check::query()->whereKey($data['check_id'])->firstOrFail();
        abort_if($check->type === CheckType::Agent, 422);
        abort_if($check->host_id !== null && $check->host_id !== $host->id, 422);

        $this->authorize('update', $check);
        $check->update(['host_id' => $host->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Monitor linked to this server.')]);

        return back();
    }

    public function detachCheck(Host $host, Check $check): RedirectResponse
    {
        $this->authorize('update', $host);
        abort_if($check->host_id !== $host->id || $check->type === CheckType::Agent, 404);

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

        $token = $host->issueEnrollmentToken();

        return response()->json([
            'command' => AgentInstallCommand::curlSh($token),
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
