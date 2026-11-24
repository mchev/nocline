<?php

namespace App\Modules\Checks\Jobs;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\RunHubCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteHubCheck implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $checkId) {}

    public function handle(RunHubCheck $runner): void
    {
        $check = Check::query()->find($this->checkId);

        if ($check === null) {
            return;
        }

        $runner->run($check);
    }
}
