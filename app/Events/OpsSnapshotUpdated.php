<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals that ops UI snapshots (dashboard, servers, monitors) should refresh.
 * Works with Inertia live props; a polling transport covers installs without Reverb.
 */
class OpsSnapshotUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('ops');
    }

    public function broadcastAs(): string
    {
        return 'OpsSnapshotUpdated';
    }
}
