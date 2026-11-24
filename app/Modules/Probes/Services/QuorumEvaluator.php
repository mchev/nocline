<?php

namespace App\Modules\Probes\Services;

use App\Modules\Checks\Models\Check;
use App\Modules\Probes\Models\CheckProbeState;
use App\Modules\Probes\Models\Probe;

class QuorumEvaluator
{
    /**
     * Aggregate fresh probe results into one verdict; null when no probe has fresh data.
     *
     * @return array{ok: bool, latency_ms: int|null, error: string|null, probes: int, down: int}|null
     */
    public function evaluate(Check $check): ?array
    {
        $fresh = now()->subSeconds(max($check->interval_sec * 3, 120));

        $states = CheckProbeState::query()
            ->where('check_id', $check->id)
            ->whereNotNull('last_ok')
            ->where('last_run_at', '>=', $fresh)
            ->whereIn('probe_id', Probe::query()->where('enabled', true)->select('id'))
            ->get();

        if ($states->isEmpty()) {
            return null;
        }

        $total = $states->count();
        $down = $states->where('last_ok', false)->count();

        $isDown = match ($check->quorum) {
            'any' => $down >= 1,
            'all' => $down === $total,
            default => $down * 2 > $total,
        };

        $latencies = $states->where('last_ok', true)->pluck('last_latency_ms')->filter()->all();

        return [
            'ok' => ! $isDown,
            'latency_ms' => $latencies ? (int) round(array_sum($latencies) / count($latencies)) : null,
            'error' => $isDown ? $states->where('last_ok', false)->pluck('last_error')->filter()->first() ?? 'Down' : null,
            'probes' => $total,
            'down' => $down,
        ];
    }
}
