<?php

namespace App\Modules\Alertes\Services;

use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Models\Check;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Routes incident notifications: empty group mapping → all enabled channels; else only linked channels.
 */
class NotificationChannelResolver
{
    /**
     * @return Collection<int, NotificationChannel>
     */
    public function forCheck(Check $check): Collection
    {
        $groupId = $check->group_id;

        if ($groupId === null) {
            return $this->allEnabled();
        }

        $channelIds = DB::table('group_notification_channel')
            ->where('group_id', $groupId)
            ->pluck('notification_channel_id');

        if ($channelIds->isEmpty()) {
            return $this->allEnabled();
        }

        return NotificationChannel::query()
            ->where('enabled', true)
            ->whereIn('id', $channelIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, NotificationChannel>
     */
    private function allEnabled(): Collection
    {
        return NotificationChannel::query()->where('enabled', true)->orderBy('name')->get();
    }
}
