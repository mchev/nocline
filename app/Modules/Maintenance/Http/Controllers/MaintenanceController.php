<?php

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Http\Requests\MaintenanceWindowRequest;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(MaintenanceChecker $checker): Response
    {
        $this->authorize('viewAny', MaintenanceWindow::class);

        $groupNames = Group::query()->pluck('name', 'id');
        $checkNames = Check::query()->orderBy('name')->limit(2000)->pluck('name', 'id');

        $format = fn (MaintenanceWindow $w, string $state, $start, $end): array => [
            'id' => $w->id,
            'name' => $w->name,
            'group_id' => $w->group_id,
            'check_id' => $w->check_id,
            'scope' => $w->check_id !== null
                ? ($checkNames[$w->check_id] ?? '')
                : ($w->group_id !== null ? ($groupNames[$w->group_id] ?? '') : null),
            'starts_at' => $w->starts_at->toIso8601String(),
            'ends_at' => $w->ends_at->toIso8601String(),
            'recurrence' => $w->recurrence->value,
            'state' => $state,
            'next_starts_at' => $start->toIso8601String(),
            'next_ends_at' => $end->toIso8601String(),
        ];

        $current = array_map(
            fn (array $o) => $format($o['window'], $o['active'] ? 'active' : 'upcoming', $o['starts_at'], $o['ends_at']),
            $checker->upcoming(days: 3650),
        );

        $past = MaintenanceWindow::query()
            ->where('recurrence', MaintenanceRecurrence::None->value)
            ->where('ends_at', '<', now())
            ->latest('ends_at')->limit(20)->get()
            ->map(fn (MaintenanceWindow $w) => $format($w, 'past', $w->starts_at, $w->ends_at))->all();

        return Inertia::render('maintenance/Index', [
            'windows' => [...$current, ...$past],
            'groups' => Group::options(),
            'checks' => Check::query()->orderBy('name')->limit(2000)->get(['id', 'name']),
        ]);
    }

    public function store(MaintenanceWindowRequest $request): RedirectResponse
    {
        $this->authorize('create', MaintenanceWindow::class);

        MaintenanceWindow::create($request->windowData() + ['created_by' => $request->user()->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance planned.')]);

        return back();
    }

    public function update(MaintenanceWindowRequest $request, MaintenanceWindow $window): RedirectResponse
    {
        $this->authorize('update', $window);

        $window->update($request->windowData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance updated.')]);

        return back();
    }

    public function destroy(MaintenanceWindow $window): RedirectResponse
    {
        $this->authorize('delete', $window);

        $window->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance deleted.')]);

        return back();
    }
}
