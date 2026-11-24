<?php

namespace App\Modules\Probes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Probes\Http\Requests\ProbeRequest;
use App\Modules\Probes\Models\Probe;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProbeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Probe::class);

        return Inertia::render('probes/Index', [
            'probes' => Probe::query()->orderBy('name')->get()->map(fn (Probe $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'role' => $p->role,
                'enabled' => $p->enabled,
                'enrolled' => $p->enrolled_at !== null,
                'online' => $p->isOnline(),
                'capabilities' => $p->capabilities ?? [],
            ]),
        ]);
    }

    public function store(ProbeRequest $request): RedirectResponse
    {
        $this->authorize('create', Probe::class);

        $probe = Probe::create($request->validated());

        return back()->with('enrollment', ['host' => $probe->name, 'token' => $probe->issueEnrollmentToken()]);
    }

    public function enrollmentToken(Probe $probe): RedirectResponse
    {
        $this->authorize('update', $probe);

        return back()->with('enrollment', ['host' => $probe->name, 'token' => $probe->issueEnrollmentToken()]);
    }

    public function revoke(Probe $probe): RedirectResponse
    {
        $this->authorize('update', $probe);

        $probe->revokeApiToken();

        return back();
    }

    public function destroy(Probe $probe): RedirectResponse
    {
        $this->authorize('delete', $probe);

        $probe->delete();

        return back();
    }
}
