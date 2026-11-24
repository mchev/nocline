<?php

namespace App\Modules\Checks\Jobs;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteFaviconSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchCheckFavicon implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $checkId) {}

    public function handle(SiteFaviconSync $favicons): void
    {
        $check = Check::query()->find($this->checkId);

        if ($check === null) {
            return;
        }

        $favicons->sync($check);
    }
}
