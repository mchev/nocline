<?php

namespace App\Modules\Maintenance\Services;

use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

/**
 * Tells which monitors are under planned maintenance. Always one query for the windows
 * (plus one for the group tree when a window targets a group), whatever the number of checks.
 */
class MaintenanceChecker
{
    public function isActive(Check $check, ?CarbonInterface $at = null): bool
    {
        return $this->activeCheckIds([$check], $at) !== [];
    }

    /**
     * @param  iterable<Check>  $checks
     * @return list<int>
     */
    public function activeCheckIds(iterable $checks, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $active = $this->candidateWindows($at)->filter(fn (MaintenanceWindow $w) => $w->isActiveAt($at))->values();

        if ($active->isEmpty()) {
            return [];
        }

        $applies = $this->scopeMatcher($active);
        $ids = [];

        foreach ($checks as $check) {
            if ($active->contains(fn (MaintenanceWindow $w) => $applies($w, $check))) {
                $ids[] = $check->id;
            }
        }

        return $ids;
    }

    /**
     * Windows in progress or starting within $days, as dated occurrences, soonest first.
     * When $checks is given, only windows touching at least one of them are returned.
     *
     * @param  iterable<Check>|null  $checks
     * @return list<array{window: MaintenanceWindow, starts_at: CarbonImmutable, ends_at: CarbonImmutable, active: bool}>
     */
    public function upcoming(?CarbonInterface $at = null, int $days = 14, ?iterable $checks = null): array
    {
        $at ??= now();
        $horizon = $at->copy()->addDays($days);
        $windows = $this->candidateWindows($at);
        $applies = $checks === null ? null : $this->scopeMatcher($windows);
        $checks = $checks === null ? null : collect($checks);
        $out = [];

        foreach ($windows as $window) {
            $occurrence = $window->occurrenceAt($at);

            if ($occurrence === null || $occurrence[0] > $horizon) {
                continue;
            }

            if ($applies !== null && ! $checks?->contains(fn (Check $c) => $applies($window, $c))) {
                continue;
            }

            $out[] = [
                'window' => $window,
                'starts_at' => $occurrence[0],
                'ends_at' => $occurrence[1],
                'active' => $occurrence[0] <= $at,
            ];
        }

        usort($out, fn (array $a, array $b) => $a['starts_at'] <=> $b['starts_at']);

        return $out;
    }

    /**
     * Windows that can be active now or later: recurring ones and one-offs not over yet.
     *
     * @return Collection<int, MaintenanceWindow>
     */
    private function candidateWindows(CarbonInterface $at): Collection
    {
        return MaintenanceWindow::query()
            ->where(fn ($q) => $q->where('recurrence', '!=', MaintenanceRecurrence::None->value)->orWhere('ends_at', '>=', $at))
            ->get();
    }

    /**
     * @param  Collection<int, MaintenanceWindow>  $windows
     * @return Closure(MaintenanceWindow, Check): bool
     */
    private function scopeMatcher(Collection $windows): Closure
    {
        $groupIdsByWindow = [];

        if ($windows->whereNotNull('group_id')->isNotEmpty()) {
            $childrenByParent = Group::query()->whereNotNull('parent_id')->get(['id', 'parent_id'])->groupBy('parent_id');

            foreach ($windows->whereNotNull('group_id') as $window) {
                $ids = [$window->group_id];

                for ($i = 0; $i < count($ids); $i++) {
                    foreach ($childrenByParent->get($ids[$i], []) as $child) {
                        $ids[] = $child->id;
                    }
                }

                $groupIdsByWindow[$window->id] = array_flip($ids);
            }
        }

        return fn (MaintenanceWindow $window, Check $check): bool => match (true) {
            $window->check_id !== null => $window->check_id === $check->id,
            $window->group_id !== null => $check->group_id !== null && isset($groupIdsByWindow[$window->id][$check->group_id]),
            default => true,
        };
    }
}
