<?php

namespace App\Modules\Renewals\Jobs;

use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Services\RenewalSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncCheckRenewals implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $checkId) {}

    public function handle(RenewalSync $sync): void
    {
        try {
            $check = Check::query()->find($this->checkId);

            if ($check !== null) {
                $sync->check($check);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
