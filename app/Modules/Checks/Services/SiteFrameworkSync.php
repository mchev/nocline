<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\SiteFrameworkTag;

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

        if ($tags === ($check->tags ?? [])) {
            return false;
        }

        $check->update(['tags' => $tags]);

        return true;
    }

    public function needsSync(Check $check): bool
    {
        return $this->appliesTo($check) && ! SiteFrameworkTag::hasFrameworkTag($check);
    }
}
