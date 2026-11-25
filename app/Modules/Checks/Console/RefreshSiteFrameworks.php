<?php

namespace App\Modules\Checks\Console;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteFrameworkSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:checks:refresh-frameworks
    {--all : Re-detect every website, not only those missing a framework tag}')]
#[Description('Detect Laravel / WordPress on website monitors, framework tags, and WordPress core version')]
class RefreshSiteFrameworks extends Command
{
    public function handle(SiteFrameworkSync $sync): int
    {
        $query = Check::query()
            ->where('enabled', true)
            ->where('type', CheckType::Http)
            ->where('category', CheckCategory::Website)
            ->orderBy('id');
        $updated = 0;
        $total = 0;

        $query->chunkById(40, function ($checks) use ($sync, &$updated, &$total): void {
            foreach ($checks as $check) {
                $total++;

                if (! $this->option('all') && ! $sync->needsSync($check)) {
                    continue;
                }

                if ($sync->sync($check)) {
                    $updated++;
                }
            }
        });

        $this->components->info("Updated framework tags on {$updated} of {$total} website(s) scanned.");

        return self::SUCCESS;
    }
}
