<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Events\CheckRecovered;
use App\Modules\Checks\Events\CheckWentDegraded;
use App\Modules\Checks\Events\CheckWentDown;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Models\CheckLatencySample;
use App\Modules\Checks\Models\CheckState;
use App\Modules\Checks\Support\HttpServiceUnavailable;
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
            $slowStreak = (int) ($state->consecutive_slow ?? 0);
            $ok = $this->applyHttpLatencyPolicy($check, $ok, $latencyMs, $slowStreak, $error);

            $failures = $ok ? 0 : $state->consecutive_failures + 1;

            if (! $ok && $error !== null && str_contains($error, 'Slow response')) {
                $failures = max($failures, $check->failure_threshold);
            }

            $maintenanceHttp = ! $ok && HttpServiceUnavailable::isMaintenanceResponse($error, $metadata);

            $next = match (true) {
                $ok => CheckStatus::Up,
                $maintenanceHttp && $failures >= $check->failure_threshold => CheckStatus::Degraded,
                $failures >= $check->failure_threshold => CheckStatus::Down,
                default => $previous,
            };

            $state->fill([
                'status' => $next,
                'consecutive_failures' => $failures,
                'consecutive_slow' => $slowStreak,
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

            CheckLatencySample::query()->create([
                'check_id' => $check->id,
                'at' => now(),
                'latency_ms' => $latencyMs,
                'ok' => $ok,
            ]);

            return $next !== $previous ? $next : null;
        });

        match ($transition) {
            CheckStatus::Down => CheckWentDown::dispatch($check, $error),
            CheckStatus::Degraded => CheckWentDegraded::dispatch($check, $error),
            CheckStatus::Up => CheckRecovered::dispatch($check),
            default => null,
        };

        OpsBroadcast::ping();

        return $transition ?? $check->state()->first()->status;
    }

    private function applyHttpLatencyPolicy(
        Check $check,
        bool $ok,
        ?int $latencyMs,
        int &$slowStreak,
        ?string &$error,
    ): bool {
        if ($check->type !== CheckType::Http || $latencyMs === null) {
            if (! $ok) {
                $slowStreak = 0;
            }

            return $ok;
        }

        $limit = (int) ($check->config['latency_alert_ms'] ?? 0);

        if ($limit <= 0) {
            if (! $ok) {
                $slowStreak = 0;
            }

            return $ok;
        }

        if (! $ok) {
            $slowStreak = 0;

            return false;
        }

        if ($latencyMs >= $limit) {
            $slowStreak++;
        } else {
            $slowStreak = 0;
        }

        $sustain = (int) ($check->config['latency_sustain'] ?? $check->failure_threshold);

        if ($slowStreak >= $sustain) {
            $message = __('Slow response (:ms ms, limit :limit ms)', [
                'ms' => $latencyMs,
                'limit' => $limit,
            ]);
            $error = is_string($message) ? $message : 'Slow response';

            return false;
        }

        return true;
    }
}
