<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckLatencySample;
use App\Modules\Checks\Support\HistoryWindow;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Latency samples for monitor detail charts (short TTL, pruned with checks).
 */
final class CheckLatencySeries
{
    public function __construct(
        private CheckStatusHistory $history,
        private MaintenanceChecker $maintenance,
    ) {}

    /**
     * @return list<array{at: string, latency_ms: int|null, ok: bool}>
     */
    public function points(Check $check, int $hours): array
    {
        $hours = max(1, min(168, $hours));
        $since = now()->subHours($hours);

        return CheckLatencySample::query()
            ->where('check_id', $check->id)
            ->where('at', '>=', $since)
            ->orderBy('at')
            ->get(['at', 'latency_ms', 'ok'])
            ->map(fn (CheckLatencySample $s) => [
                'at' => $s->at->toIso8601String(),
                'latency_ms' => $s->latency_ms,
                'ok' => $s->ok,
            ])
            ->all();
    }

    public function averageLatencyMs(Check $check, int $hours): ?float
    {
        $since = now()->subHours(max(1, $hours));

        $avg = CheckLatencySample::query()
            ->where('check_id', $check->id)
            ->where('at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    public function downIntervals(Check $check, int $hours): array
    {
        $window = new HistoryWindow(max(12, min(336, $hours * 4)), (float) $hours);
        $hist = $this->history->forChecks(
            new EloquentCollection([$check]),
            $this->maintenance,
            null,
            $window,
        )[$check->id] ?? null;

        if ($hist === null || $hist['timeline'] === []) {
            return [];
        }

        $intervals = [];
        $openStart = null;
        $bucketSeconds = $window->bucketSeconds();

        foreach ($hist['timeline'] as $bucket) {
            $at = CarbonImmutable::parse($bucket['at']);
            $isDown = ($bucket['s'] ?? '') === 'down';

            if ($isDown && $openStart === null) {
                $openStart = $at->subSeconds($bucketSeconds);
            } elseif (! $isDown && $openStart !== null) {
                $intervals[] = [
                    'start' => $openStart->toIso8601String(),
                    'end' => $at->toIso8601String(),
                ];
                $openStart = null;
            }
        }

        if ($openStart !== null) {
            $intervals[] = [
                'start' => $openStart->toIso8601String(),
                'end' => now()->toIso8601String(),
            ];
        }

        return $intervals;
    }

    public function uptimePct(Check $check, float $hours): ?float
    {
        $buckets = (int) max(24, min(720, round($hours)));
        $window = new HistoryWindow($buckets, $hours);
        $hist = $this->history->forChecks(
            new EloquentCollection([$check]),
            $this->maintenance,
            null,
            $window,
        )[$check->id] ?? null;

        return $hist['uptime_24h_pct'] ?? null;
    }
}
