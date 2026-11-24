<?php

namespace App\Modules\Renewals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Renewals\Jobs\RefreshAllRenewals;
use App\Modules\Renewals\Models\Renewal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RenewalController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Renewal::class);

        return Inertia::render('renewals/Index', [
            'renewals' => Renewal::query()->with(['group:id,name', 'check:id,name'])->orderBy('expires_on')->get()
                ->map(fn (Renewal $r) => [
                    'id' => $r->id,
                    'kind' => $r->kind,
                    'label' => $r->label,
                    'site' => $r->check?->name,
                    'expires_on' => $r->expires_on->toDateString(),
                    'days_remaining' => $r->daysRemaining(),
                    'group' => $r->group?->name,
                    'source' => $r->source,
                    'last_checked_at' => $r->last_checked_at?->toIso8601String(),
                ]),
            'can_refresh' => (bool) request()->user()?->can('refresh', Renewal::class),
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $this->authorize('refresh', Renewal::class);

        RefreshAllRenewals::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refresh started. Dates will update in a moment.')]);

        return back();
    }
}
