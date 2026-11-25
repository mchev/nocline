<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Hosts\Models\Host;

/**
 * One internal check per host; drives incidents when CPU/RAM/disk/load thresholds are breached.
 */
class HostResourceCheckProvisioner
{
    public function ensure(Host $host): ?Check
    {
        if ($host->enrolled_at === null) {
            return null;
        }

        $existing = Check::query()
            ->where('host_id', $host->id)
            ->where('type', CheckType::HostResources)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Check::create([
            'name' => $host->name.' — '.__('Server load'),
            'type' => CheckType::HostResources,
            'category' => CheckCategory::Server,
            'host_id' => $host->id,
            'group_id' => $host->group_id,
            'target' => '',
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 1,
            'enabled' => true,
        ]);
    }
}
