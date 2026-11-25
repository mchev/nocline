<?php

namespace App\Modules\Checks\Console;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteFaviconSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:checks:refresh-favicons
    {--all : Refresh every website check, not only stale ones}
    {--stale-days=7 : Re-fetch when older than this many days}')]
#[Description('Re-download website favicons and flag when the icon fingerprint changes')]
class RefreshCheckFavicons extends Command
{
    public function handle(SiteFaviconSync $favicons): int
    {
        $query = Check::query()
            ->where('type', CheckType::Http)
            ->where('category', CheckCategory::Website)
            ->whereNotNull('target')
            ->where('enabled', true);

        if (! $this->option('all')) {
            $staleDays = max(1, (int) $this->option('stale-days'));
            $query->where(function ($q) use ($staleDays): void {
                $q->whereNull('favicon_fetched_at')
                    ->orWhere('favicon_fetched_at', '<', now()->subDays($staleDays));
            });
        }

        $updated = 0;
        $total = 0;

        $query->orderBy('id')->chunkById(50, function ($checks) use ($favicons, &$updated, &$total): void {
            foreach ($checks as $check) {
                $total++;

                if ($favicons->sync($check)) {
                    $updated++;
                }
            }
        });

        $this->components->info("Refreshed {$updated} of {$total} website favicon(s).");

        return self::SUCCESS;
    }
}
