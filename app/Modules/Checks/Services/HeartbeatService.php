<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckState;
use Carbon\CarbonInterface;

/**
 * Heartbeat (dead man's switch) checks: a scheduled job pings Nocline; silence past interval + grace means down.
 * Everything goes through CheckResultRecorder so events, incidents and alerts behave as for any other check.
 */
class HeartbeatService
{
    public function __construct(private readonly CheckResultRecorder $recorder) {}

    /**
     * Record a ping from the monitored job: a success, or an explicit failure with an optional message.
     */
    public function ping(Check $check, bool $ok = true, ?string $message = null): void
    {
        $this->recorder->record(
            $check,
            $ok,
            error: $ok ? null : (mb_substr(trim((string) $message), 0, 200) ?: 'The job reported a failure'),
            metadata: ['source' => 'heartbeat'],
        );

        CheckState::query()->whereKey($check->id)->update(['last_ping_at' => now()]);
    }

    /**
     * Mark the heartbeat as failed when no ping arrived in time. Returns whether it was marked.
     *
     * A check that never got a first ping is measured from its creation date.
     */
    public function failIfOverdue(Check $check): bool
    {
        $state = $check->state;

        if ($state === null || $state->status === CheckStatus::Down || ! $this->deadline($check, $state)->isPast()) {
            return false;
        }

        $this->recorder->record($check, false, error: 'No ping received in time', metadata: ['source' => 'heartbeat']);

        return true;
    }

    public function deadline(Check $check, CheckState $state): CarbonInterface
    {
        return ($state->last_ping_at ?? $check->created_at)
            ->copy()
            ->addSeconds($check->interval_sec + $check->heartbeatGraceSeconds());
    }
}
