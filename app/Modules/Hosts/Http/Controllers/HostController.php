<?php

namespace App\Modules\Hosts\Http\Controllers;

use App\Events\OpsSnapshotUpdated;
use App\Http\Controllers\Controller;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Http\Requests\HostRequest;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Services\HostMetricsReader;
use App\Modules\Updates\Services\ReleaseChecker;
use Illuminate\Http\RedirectResponse;
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
                ])->values();
            }, $live),
            'groups' => Group::options(),
        ]);
    }

    public function store(HostRequest $request): RedirectResponse
    {
        $this->authorize('create', Host::class);

        $host = Host::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Host created. Copy the enrollment command below.')]);

        return back()->with('enrollment', [
            'host' => $host->name,
            'token' => $host->issueEnrollmentToken(),
        ]);
    }

    public function update(HostRequest $request, Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $host->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Host updated.')]);

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
