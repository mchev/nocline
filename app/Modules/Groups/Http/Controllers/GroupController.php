<?php

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Http\Requests\GroupRequest;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Group::class);

        $counts = Check::query()->whereNotNull('group_id')->selectRaw('group_id, count(*) as total')
            ->groupBy('group_id')->pluck('total', 'group_id');
        $channelIdsByGroup = DB::table('group_notification_channel')
            ->get()
            ->groupBy('group_id')
            ->map(fn ($rows) => $rows->pluck('notification_channel_id')->map(fn ($id) => (int) $id)->values()->all());

        return Inertia::render('groups/Index', [
            'notification_channels' => NotificationChannel::query()
                ->orderBy('name')
                ->get(['id', 'name', 'enabled'])
                ->map(fn (NotificationChannel $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'enabled' => $c->enabled,
                ])
                ->values(),
            'groups' => array_map(
                fn (array $g) => $g + [
                    'checks_count' => (int) ($counts[$g['id']] ?? 0),
                    'channel_ids' => $channelIdsByGroup->get($g['id'], []),
                ],
                Group::options(),
            ),
        ]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $data = $request->validated();
        $channelIds = $data['channel_ids'] ?? null;
        unset($data['channel_ids']);

        $group = Group::create($data);
        $this->syncGroupChannels($group, $channelIds);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group added.')]);

        return back();
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $data = $request->validated();
        $channelIds = $data['channel_ids'] ?? null;
        unset($data['channel_ids']);

        $group->update($data);
        if ($channelIds !== null) {
            $this->syncGroupChannels($group, $channelIds);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group updated.')]);

        return back();
    }

    /**
     * @param  list<int>|null  $channelIds  empty array = use all channels when alerting
     */
    private function syncGroupChannels(Group $group, ?array $channelIds): void
    {
        if ($channelIds === null) {
            return;
        }

        DB::table('group_notification_channel')->where('group_id', $group->id)->delete();

        foreach ($channelIds as $channelId) {
            DB::table('group_notification_channel')->insert([
                'group_id' => $group->id,
                'notification_channel_id' => $channelId,
            ]);
        }
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
