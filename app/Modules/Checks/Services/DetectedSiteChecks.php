<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;

/**
 * Extra monitoring proposed by the site detection when a website is added.
 */
class DetectedSiteChecks
{
    /**
     * @return array<int, string>
     */
    public function tags(?string $framework): array
    {
        return in_array($framework, SiteFramework::taggable(), true) ? [$framework] : [];
    }

    /**
     * The Laravel health page (/up) of the same origin as the main check: built here, never taken from the client.
     */
    public function createHealthCheck(Check $site): Check
    {
        $parts = parse_url($site->target);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return Check::create([
            'name' => "{$site->name} — health",
            'type' => CheckType::Http,
            'category' => CheckCategory::Website,
            'target' => $origin.'/up',
            'group_id' => $site->group_id,
            'tags' => [SiteFramework::Laravel->value],
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 2,
        ]);
    }
}
