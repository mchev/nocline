<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds short-term uptime from {@see CheckEvent} transitions (no rollup table yet).
 *
 * @phpstan-type HistoryDot 'up'|'down'|'unknown'|'maintenance'
 */
class CheckStatusHistory
{
    public const BUCKETS = 48;

    public const HOURS = 24;

    /**
     * @param  Collection<int, Check>  $checks
     * @return array<int, array{history: list<HistoryDot>, uptime_24h_pct: float|null}>
     */
    public function forChecks(Collection $checks, MaintenanceChecker $maintenance, ?CarbonInterface $now = null): array
    {
        if ($checks->isEmpty()) {
            return [];
        }

        $checks->loadMissing('state');

        $now = CarbonImmutable::instance($now ?? now());
        $windowStart = $now->subHours(self::HOURS);
        $bucketSeconds = (int) (self::HOURS * 3600 / self::BUCKETS);
        /** @var list<int> $ids */
        $ids = $checks->pluck('id')->all();

        $inRange = CheckEvent::query()
            ->whereIn('check_id', $ids)
            ->where('at', '>=', $windowStart)
            ->orderBy('at')
            ->get(['check_id', 'from_status', 'to_status', 'at'])
            ->groupBy('check_id');

        $prior = CheckEvent::query()
            ->whereIn('check_id', $ids)
            ->where('at', '<', $windowStart)
            ->orderBy('at')
            ->get(['check_id', 'from_status', 'to_status', 'at'])
            ->groupBy('check_id')
            ->map(fn (Collection $rows) => $rows->last());

        $maintenanceByBucket = [];
        for ($i = 0; $i < self::BUCKETS; $i++) {
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
            $observed = 0;
            $good = 0;

            $events = $inRange->get($check->id, collect());
            $eventIndex = 0;
            $eventCount = $events->count();

            $status = $this->statusAtWindowStart($check, $prior->get($check->id), $windowStart);

            for ($i = 0; $i < self::BUCKETS; $i++) {
                $bucketEnd = $windowStart->addSeconds(($i + 1) * $bucketSeconds);

                while ($eventIndex < $eventCount) {
                    /** @var CheckEvent $event */
                    $event = $events[$eventIndex];
                    $eventAt = CarbonImmutable::instance($event->at);

                    if ($eventAt > $bucketEnd) {
                        break;
                    }

                    $status = $event->to_status;
                    $eventIndex++;
                }

                if ($bucketEnd <= $createdAt) {
                    $dots[] = 'unknown';

                    continue;
                }

                $observed++;

                if (isset($maintenanceByBucket[$i][$check->id])) {
                    $dots[] = 'maintenance';
                    $good++;

                    continue;
                }

                $dot = match ($status) {
                    CheckStatus::Up => 'up',
                    CheckStatus::Down => 'down',
                    default => 'unknown',
                };
                $dots[] = $dot;

                if ($dot === 'up') {
                    $good++;
                }
            }

            $pct = $observed > 0
                ? (float) (floor($good / $observed * 1000) / 10)
                : null;

            $out[$check->id] = [
                'history' => $dots,
                'uptime_24h_pct' => $pct,
            ];
        }

        return $out;
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
