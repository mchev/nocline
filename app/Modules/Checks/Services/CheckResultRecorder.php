<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Events\CheckRecovered;
use App\Modules\Checks\Events\CheckWentDown;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Models\CheckState;
use App\Support\OpsBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Applies a check result to the current state; writes an event only on a status transition.
 */
class CheckResultRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(Check $check, bool $ok, ?int $latencyMs = null, ?string $error = null, array $metadata = []): CheckStatus
    {
        $transition = DB::transaction(function () use ($check, $ok, $latencyMs, $error, $metadata): ?CheckStatus {
            $state = CheckState::query()->lockForUpdate()->find($check->id)
                ?? new CheckState(['check_id' => $check->id]);

            $previous = $state->status;
            $failures = $ok ? 0 : $state->consecutive_failures + 1;

            $next = match (true) {
                $ok => CheckStatus::Up,
                $failures >= $check->failure_threshold => CheckStatus::Down,
                default => $previous,
            };

            $state->fill([
                'status' => $next,
                'consecutive_failures' => $failures,
                'last_run_at' => now(),
                'last_latency_ms' => $latencyMs,
                'last_error' => $ok ? null : $error,
                'next_run_at' => now()->addSeconds($check->interval_sec),
            ]);

            if ($next !== $previous) {
                $state->since_at = now();
                CheckEvent::create([
                    'check_id' => $check->id,
                    'from_status' => $previous,
                    'to_status' => $next,
                    'at' => now(),
                    'metadata' => $metadata + array_filter(['error' => $error, 'latency_ms' => $latencyMs]),
                ]);
            }

            $state->save();

            return $next !== $previous ? $next : null;
        });

        match ($transition) {
            CheckStatus::Down => CheckWentDown::dispatch($check, $error),
            CheckStatus::Up => CheckRecovered::dispatch($check),
            default => null,
        };

        OpsBroadcast::ping();

        return $transition ?? $check->state()->first()->status;
    }
}
