<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\SiteFrameworkTag;
use App\Modules\Checks\Support\WordPressSiteMeta;
use Illuminate\Support\Carbon;

/**
 * Detects Laravel / WordPress and stores it as check tags (visible badges in the UI).
 */
class SiteFrameworkSync
{
    public function __construct(private readonly SiteDetector $detector) {}

    public function appliesTo(Check $check): bool
    {
        return $check->type === CheckType::Http
            && $check->category === CheckCategory::Website
            && $check->target !== '';
    }

    public function sync(Check $check): bool
    {
        if (! $this->appliesTo($check)) {
            return false;
        }

        $inspection = $this->detector->inspect($check->target);

        if (! $inspection->reachable) {
            return false;
        }

        $framework = $inspection->framework;

        if ($framework === SiteFramework::Unknown) {
            return false;
        }

        $tags = collect($check->tags ?? [])
            ->reject(fn (string $tag): bool => in_array($tag, SiteFramework::taggable(), true))
            ->push($framework->value)
            ->unique()
            ->values()
            ->all();

        $updates = [];

        if ($tags !== ($check->tags ?? [])) {
            $updates['tags'] = $tags;
        }

        if ($framework === SiteFramework::WordPress) {
            $config = $check->config ?? [];
            $previous = $config['wordpress'] ?? [];
            $next = $previous;
            $next['detected_at'] = now()->toIso8601String();

            if ($inspection->wordpressVersion !== null) {
                $next['version'] = $inspection->wordpressVersion;
            }

            if ($next !== $previous) {
                $config['wordpress'] = $next;
                $updates['config'] = $config;
            }
        }

        if ($updates === []) {
            return false;
        }

        $check->update($updates);

        return true;
    }

    public function needsSync(Check $check): bool
    {
        if (! $this->appliesTo($check)) {
            return false;
        }

        if (! SiteFrameworkTag::hasFrameworkTag($check)) {
            return true;
        }

        return $this->shouldRefreshWordPressMeta($check);
    }

    public function shouldRefreshWordPressMeta(Check $check, int $staleDays = 7): bool
    {
        if (SiteFrameworkTag::fromCheck($check) !== SiteFramework::WordPress) {
            return false;
        }

        if (WordPressSiteMeta::version($check) === null) {
            return true;
        }

        $detectedAt = WordPressSiteMeta::detectedAtIso($check);

        if ($detectedAt === null) {
            return true;
        }

        return Carbon::parse($detectedAt)->lt(now()->subDays($staleDays));
    }
}
