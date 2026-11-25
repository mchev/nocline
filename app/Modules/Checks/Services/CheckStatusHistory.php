<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Support\HistoryWindow;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds short-term uptime from {@see CheckEvent} transitions (no rollup table yet).
 *
 * @phpstan-type HistoryDot 'up'|'down'|'unknown'|'maintenance'|'warning'
 */
class CheckStatusHistory
{
    public const BUCKETS = 48;

    public const HOURS = 24;

    /**
     * @param  Collection<int, Check>  $checks
     * @return array<int, array{
     *     history: list<HistoryDot>,
     *     timeline: list<array{s: HistoryDot, at: string, error: ?string}>,
     *     uptime_24h_pct: float|null
     * }>
     */
    public function forChecks(
        Collection $checks,
        MaintenanceChecker $maintenance,
        ?CarbonInterface $now = null,
        ?HistoryWindow $window = null,
    ): array {
        if ($checks->isEmpty()) {
            return [];
        }

        $window ??= HistoryWindow::standard();
        $checks->loadMissing('state');

        $now = CarbonImmutable::instance($now ?? now());
        $windowStart = $now->subSeconds((int) round($window->hours * 3600));
        $bucketSeconds = $window->bucketSeconds();
        $bucketCount = $window->buckets;
        /** @var list<int> $ids */
        $ids = $checks->pluck('id')->all();

        $inRange = CheckEvent::query()
            ->whereIn('check_id', $ids)
            ->where('at', '>=', $windowStart)
            ->orderBy('at')
            ->get(['check_id', 'from_status', 'to_status', 'at', 'metadata'])
            ->groupBy('check_id');

        $prior = CheckEvent::query()
            ->whereIn('check_id', $ids)
            ->where('at', '<', $windowStart)
            ->orderBy('at')
            ->get(['check_id', 'from_status', 'to_status', 'at'])
            ->groupBy('check_id')
            ->map(fn (Collection $rows) => $rows->last());

        $maintenanceByBucket = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $at = $windowStart->addSeconds(($i + 1) * $bucketSeconds);
            $maintenanceByBucket[$i] = array_flip($maintenance->activeCheckIds($checks, $at));
        }

        $out = [];

        foreach ($checks as $check) {
            $createdAt = $check->created_at !== null
                ? CarbonImmutable::instance($check->created_at)
                : $windowStart;

            /** @var list<HistoryDot> $dots */
            $dots = [];
            /** @var list<array{s: HistoryDot, at: string, error: ?string}> $timeline */
            $timeline = [];
            $observed = 0;
            $good = 0;

            $events = $inRange->get($check->id, collect());
            $eventIndex = 0;
            $eventCount = $events->count();

            $status = $this->statusAtWindowStart($check, $prior->get($check->id), $windowStart);
            $lastError = null;

            for ($i = 0; $i < $bucketCount; $i++) {
                $bucketEnd = $windowStart->addSeconds(($i + 1) * $bucketSeconds);

                while ($eventIndex < $eventCount) {
                    /** @var CheckEvent $event */
                    $event = $events[$eventIndex];
                    $eventAt = CarbonImmutable::instance($event->at);

                    if ($eventAt > $bucketEnd) {
                        break;
                    }

                    $status = $event->to_status;

                    if ($status === CheckStatus::Down || $status === CheckStatus::Degraded) {
                        $err = $event->metadata['error'] ?? null;
                        $lastError = is_string($err) && $err !== '' ? $err : $lastError;
                    } elseif ($status === CheckStatus::Up) {
                        $lastError = null;
                    }

                    $eventIndex++;
                }

                if ($bucketEnd <= $createdAt) {
                    if ($window->imputePreCreation) {
                        $dot = $this->historyDotFromStatus(
                            $check->state->status ?? CheckStatus::Unknown,
                        );
                        $dots[] = $dot;
                        $timeline[] = [
                            's' => $dot,
                            'at' => $bucketEnd->toIso8601String(),
                            'error' => null,
                        ];
                    } else {
                        $dots[] = 'unknown';
                        $timeline[] = [
                            's' => 'unknown',
                            'at' => $bucketEnd->toIso8601String(),
                            'error' => null,
                        ];
                    }

                    continue;
                }

                $observed++;

                if (isset($maintenanceByBucket[$i][$check->id])) {
                    $dots[] = 'maintenance';
                    $timeline[] = [
                        's' => 'maintenance',
                        'at' => $bucketEnd->toIso8601String(),
                        'error' => null,
                    ];
                    $good++;

                    continue;
                }

                $dot = match ($status) {
                    CheckStatus::Up => 'up',
                    CheckStatus::Down => 'down',
                    CheckStatus::Degraded => 'warning',
                    default => 'unknown',
                };
                $dots[] = $dot;

                $error = null;
                if ($dot === 'down' || $dot === 'warning') {
                    $error = $lastError ?? $check->state?->last_error;
                    $error = is_string($error) && $error !== '' ? $error : null;
                }

                $timeline[] = [
                    's' => $dot,
                    'at' => $bucketEnd->toIso8601String(),
                    'error' => $error,
                ];

                if ($dot === 'up' || $dot === 'warning') {
                    $good++;
                }
            }

            $pct = $observed > 0
                ? (float) (floor($good / $observed * 1000) / 10)
                : null;

            $out[$check->id] = [
                'history' => $dots,
                'timeline' => $timeline,
                'uptime_24h_pct' => $pct,
            ];
        }

        return $out;
    }

    /**
     * @return HistoryDot
     */
    private function historyDotFromStatus(CheckStatus $status): string
    {
        return match ($status) {
            CheckStatus::Up => 'up',
            CheckStatus::Down => 'down',
            CheckStatus::Degraded => 'warning',
            default => 'unknown',
        };
    }

    private function statusAtWindowStart(Check $check, ?CheckEvent $lastBefore, CarbonImmutable $windowStart): CheckStatus
    {
        if ($lastBefore !== null) {
            return $lastBefore->to_status;
        }

        if ($check->created_at !== null && CarbonImmutable::instance($check->created_at) > $windowStart) {
            return CheckStatus::Unknown;
        }

        return $check->state->status ?? CheckStatus::Unknown;
    }
}
