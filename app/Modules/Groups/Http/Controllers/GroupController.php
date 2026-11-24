<?php

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Http\Requests\GroupRequest;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Group::class);

        $counts = Check::query()->whereNotNull('group_id')->selectRaw('group_id, count(*) as total')
            ->groupBy('group_id')->pluck('total', 'group_id');

        return Inertia::render('groups/Index', [
            'groups' => array_map(
                fn (array $g) => $g + ['checks_count' => (int) ($counts[$g['id']] ?? 0)],
                Group::options(),
            ),
        ]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        Group::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group added.')]);

        return back();
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $group->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group updated.')]);

        return back();
    }

    /**
     * Sub-groups move up one level; the monitors it held become ungrouped.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->dissolve();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group deleted.')]);

        return back();
    }
}
