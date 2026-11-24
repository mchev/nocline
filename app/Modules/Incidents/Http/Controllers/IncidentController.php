<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Incident::class);

        return Inertia::render('incidents/Index', [
            'incidents' => Incident::query()
                ->with(['check:id,name', 'acknowledgedBy:id,name'])
                ->orderByRaw('resolved_at is not null')
                ->latest('started_at')
                ->paginate(25)
                ->through(fn (Incident $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'check' => $i->check->name,
                    'started_at' => $i->started_at->toIso8601String(),
                    'resolved_at' => $i->resolved_at?->toIso8601String(),
                    'acknowledged_at' => $i->acknowledged_at?->toIso8601String(),
                    'acknowledged_by' => $i->acknowledgedBy?->name,
                    'last_error' => $i->last_error,
                ]),
        ]);
    }

    public function acknowledge(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorize('acknowledge', $incident);

        if ($incident->isOpen() && ! $incident->isAcknowledged()) {
            $incident->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $request->user()->id,
            ]);
        }

        return back();
    }
}
