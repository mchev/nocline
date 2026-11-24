<?php

namespace App\Modules\Renewals\Jobs;

use App\Modules\Renewals\Services\RenewalSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshAllRenewals implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function handle(RenewalSync $sync): void
    {
        try {
            $sync->all();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
