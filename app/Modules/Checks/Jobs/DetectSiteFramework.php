<?php

namespace App\Modules\Checks\Jobs;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteFrameworkSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectSiteFramework implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $checkId) {}

    public function handle(SiteFrameworkSync $sync): void
    {
        $check = Check::query()->find($this->checkId);

        if ($check === null || ! $sync->needsSync($check)) {
            return;
        }

        $sync->sync($check);
    }
}
