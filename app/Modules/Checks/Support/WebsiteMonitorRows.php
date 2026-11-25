<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckStatusHistory;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Renewals\Models\Renewal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Dashboard and wallboard rows for website monitors (status, 24 min history, SSL, favicon).
 */
final class WebsiteMonitorRows
{
    public function __construct(
        private MaintenanceChecker $maintenance,
        private CheckStatusHistory $history,
    ) {}

    /**
     * @param  EloquentCollection<int, Check>  $sites
     * @return list<array<string, mixed>>
     */
    public function rows(EloquentCollection $sites): array
    {
        if ($sites->isEmpty()) {
            return [];
        }

        $siteMaint = array_flip($this->maintenance->activeCheckIds($sites));
        $histories = $this->history->forChecks($sites, $this->maintenance, null, HistoryWindow::dashboard());
        $sslRenewals = Renewal::query()
            ->where('kind', 'ssl')
            ->whereIn('check_id', $sites->pluck('id'))
            ->get()
            ->keyBy('check_id');

        /** @var list<array<string, mixed>> $rows */
        $rows = $sites
            ->map(function (Check $c) use ($siteMaint, $histories, $sslRenewals) {
                $ssl = $sslRenewals->get($c->id);

                return [
                    'id' => $c->id,
                    ...SiteHealthCompanion::linkMeta($c),
                    'name' => $c->name,
                    'target' => $c->target,
                    'status' => $c->state?->status->value ?? 'unknown',
                    'latency_ms' => $c->state?->last_latency_ms,
                    'in_maintenance' => isset($siteMaint[$c->id]),
                    'enabled' => $c->enabled,
                    'history' => $histories[$c->id]['history'] ?? [],
                    'timeline' => $histories[$c->id]['timeline'] ?? [],
                    'uptime_24h_pct' => $histories[$c->id]['uptime_24h_pct'] ?? null,
                    'favicon_url' => $c->faviconUrl(),
                    'ssl_status' => WebsiteSslStatus::forCheck($c, $ssl),
                    'ssl_days_remaining' => $ssl !== null && $ssl->check_error === null ? $ssl->daysRemaining() : null,
                    'site_framework' => SiteFrameworkTag::fromCheck($c)?->value,
                ];
            })
            ->sortBy([
                fn (array $row) => self::problemRank($row['enabled'], $row['in_maintenance'], $row['status']),
                fn (array $row) => $row['name'],
            ])
            ->values()
            ->all();

        return $rows;
    }

    public static function problemRank(bool $enabled, bool $inMaintenance, string $status): int
    {
        if (! $enabled) {
            return 4;
        }

        if ($inMaintenance) {
            return 3;
        }

        return match ($status) {
            CheckStatus::Down->value => 0,
            CheckStatus::Unknown->value => 1,
            default => 2,
        };
    }
}
