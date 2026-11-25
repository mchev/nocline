<?php

namespace App\Modules\StatusPages\Services;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Groups\Models\Group;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\StatusPages\Models\StatusPage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the cached, public-safe payload of a status page. It deliberately carries
 * monitor names and states only: never targets, errors, hosts or internal ids.
 *
 * @phpstan-type Monitor array{name: string, status: string, uptime: float|null}
 * @phpstan-type Node array{name: string, color: string|null, monitors: list<Monitor>, groups: list<array<string, mixed>>}
 */
class StatusPagePayload
{
    public const CACHE_TTL_SECONDS = 30;

    public const UPTIME_DAYS = 90;

    public const INCIDENT_DAYS = 14;

    /** Below this observed time (seconds) a percentage would be meaningless. */
    private const MIN_UPTIME_OBSERVATION = 3600;

    public function __construct(private MaintenanceChecker $maintenance) {}

    public static function forget(string $slug): void
    {
        Cache::forget(self::cacheKey($slug));
    }

    /**
     * @return array<string, mixed>
     */
    public function forPage(StatusPage $page): array
    {
        return Cache::remember(self::cacheKey($page->slug), self::CACHE_TTL_SECONDS, fn () => $this->build($page));
    }

    private static function cacheKey(string $slug): string
    {
        return 'status-page:'.$slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(StatusPage $page): array
    {
        $now = CarbonImmutable::now();
        $groups = Group::query()->orderBy('position')->orderBy('name')->get(['id', 'parent_id', 'name', 'color']);
        $scopeIds = $page->group_id === null ? null : $this->descendantIds($groups, $page->group_id);

        $checks = Check::query()
            ->where('enabled', true)
            ->when($scopeIds, fn ($q) => $q->whereIn('group_id', $scopeIds))
            ->with('state')
            ->orderBy('name')
            ->get();

        $inMaintenance = array_flip($this->maintenance->activeCheckIds($checks, $now));
        $uptimes = $this->uptimes($checks, $now);

        /** @var Collection<array-key, list<Monitor>> $monitorsByGroup */
        $monitorsByGroup = $checks->groupBy(fn (Check $c) => $c->group_id ?? 0)->map(
            fn (Collection $items) => $items->map(fn (Check $c): array => [
                'name' => $c->name,
                'status' => $this->publicStatus($c, isset($inMaintenance[$c->id])),
                'uptime' => $uptimes[$c->id] ?? null,
            ])->values()->all(),
        );

        $childrenByParent = $groups->toBase()->groupBy(fn (Group $g) => $g->parent_id ?? 0);
        $rootId = $page->group_id ?? 0;

        $windows = $this->maintenance->upcoming($now, 14, $checks);

        return [
            'title' => $page->title,
            'description' => $page->description,
            'logo_url' => $page->logoUrl(),
            'accent_color' => $page->accent_color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $page->accent_color) === 1
                ? $page->accent_color
                : null,
            'generated_at' => $now->toIso8601String(),
            'overall' => $this->overall($checks, $monitorsByGroup),
            'monitors' => $monitorsByGroup->get($rootId, []),
            'groups' => $this->tree($rootId, $childrenByParent, $monitorsByGroup),
            'maintenance' => array_map(fn (array $o): array => [
                'name' => $o['window']->name,
                'starts_at' => $o['starts_at']->toIso8601String(),
                'ends_at' => $o['ends_at']->toIso8601String(),
                'active' => $o['active'],
            ], $windows),
            'incidents' => $page->show_incidents ? $this->incidents($checks, $now) : [],
            'show_incidents' => $page->show_incidents,
            'uptime_days' => self::UPTIME_DAYS,
        ];
    }

    private function publicStatus(Check $check, bool $inMaintenance): string
    {
        if ($inMaintenance) {
            return 'maintenance';
        }

        return match ($check->state ? $check->state->status : CheckStatus::Unknown) {
            CheckStatus::Up => 'operational',
            CheckStatus::Down => 'down',
            CheckStatus::Degraded => 'degraded',
            CheckStatus::Unknown => 'unknown',
        };
    }

    /**
     * @param  EloquentCollection<int, Check>  $checks
     * @param  Collection<array-key, list<Monitor>>  $monitorsByGroup
     */
    private function overall(Collection $checks, Collection $monitorsByGroup): string
    {
        $statuses = $monitorsByGroup->flatten(1)->pluck('status');

        return match (true) {
            $checks->isEmpty() => 'unknown',
            $statuses->contains('down') || $statuses->contains('degraded') => 'degraded',
            $statuses->contains('maintenance') => 'maintenance',
            default => 'operational',
        };
    }

    /**
     * Sub-groups as nested sections; groups holding no monitor (directly or below) are left out.
     *
     * @param  Collection<array-key, Collection<int, Group>>  $childrenByParent
     * @param  Collection<array-key, list<Monitor>>  $monitorsByGroup
     * @return list<array<string, mixed>>
     */
    private function tree(int $parentId, Collection $childrenByParent, Collection $monitorsByGroup): array
    {
        $nodes = [];

        foreach ($childrenByParent->get($parentId, []) as $group) {
            $children = $this->tree($group->id, $childrenByParent, $monitorsByGroup);
            $monitors = $monitorsByGroup->get($group->id, []);

            if ($monitors === [] && $children === []) {
                continue;
            }

            $nodes[] = [
                'name' => $group->name,
                'color' => $group->color,
                'monitors' => $monitors,
                'groups' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @return list<int>
     */
    private function descendantIds(Collection $groups, int $groupId): array
    {
        $childrenByParent = $groups->whereNotNull('parent_id')->groupBy('parent_id');
        $ids = [$groupId];

        for ($i = 0; $i < count($ids); $i++) {
            foreach ($childrenByParent->get($ids[$i], []) as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Share of the last 90 days a monitor was not down, rebuilt from its status transitions
     * (check_events): no extra storage. Null while there is too little history.
     *
     * @param  EloquentCollection<int, Check>  $checks
     * @return array<int, float>
     */
    private function uptimes(EloquentCollection $checks, CarbonImmutable $now): array
    {
        if ($checks->isEmpty()) {
            return [];
        }

        $from = $now->subDays(self::UPTIME_DAYS);
        $events = CheckEvent::query()
            ->whereIn('check_id', $checks->modelKeys())
            ->where('at', '>=', $from)
            ->orderBy('at')
            ->get(['check_id', 'from_status', 'to_status', 'at'])
            ->groupBy('check_id');

        $uptimes = [];

        foreach ($checks as $check) {
            $start = $check->created_at !== null && $check->created_at > $from ? CarbonImmutable::instance($check->created_at) : $from;
            $total = $start->diffInSeconds($now);

            if ($total < self::MIN_UPTIME_OBSERVATION) {
                continue;
            }

            $checkEvents = $events->get($check->id, collect());
            $first = $checkEvents->first();
            $status = $first !== null
                ? $first->from_status
                : ($check->state ? $check->state->status : CheckStatus::Unknown);
            $cursor = $start;
            $downSeconds = 0;

            foreach ($checkEvents as $event) {
                $at = CarbonImmutable::instance($event->at);

                if ($status === CheckStatus::Down && $at > $cursor) {
                    $downSeconds += $cursor->diffInSeconds($at);
                }

                $status = $event->to_status;
                $cursor = $at > $cursor ? $at : $cursor;
            }

            if ($status === CheckStatus::Down) {
                $downSeconds += $cursor->diffInSeconds($now);
            }

            $uptimes[$check->id] = round(max(0, 100 - $downSeconds / $total * 100), 2);
        }

        return $uptimes;
    }

    /**
     * @param  EloquentCollection<int, Check>  $checks
     * @return list<array{monitor: string, started_at: string, resolved_at: string|null}>
     */
    private function incidents(EloquentCollection $checks, CarbonImmutable $now): array
    {
        if ($checks->isEmpty()) {
            return [];
        }

        $incidents = Incident::query()
            ->whereIn('check_id', $checks->modelKeys())
            ->where('started_at', '>=', $now->subDays(self::INCIDENT_DAYS))
            ->with('check:id,name')
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn (Incident $i): array => [
                'monitor' => $i->check->name,
                'started_at' => $i->started_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
            ])->all();

        return array_values($incidents);
    }
}
