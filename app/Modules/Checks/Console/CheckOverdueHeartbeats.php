<?php

namespace App\Modules\Checks\Console;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\HeartbeatService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:heartbeats:check')]
#[Description('Mark heartbeat checks as down when no ping arrived within interval + grace')]
class CheckOverdueHeartbeats extends Command
{
    public function handle(HeartbeatService $heartbeats): int
    {
        $count = 0;

        Check::query()
            ->where('enabled', true)
            ->where('type', CheckType::Heartbeat)
            ->with('state')
            ->each(function (Check $check) use ($heartbeats, &$count): void {
                if ($heartbeats->failIfOverdue($check)) {
                    $count++;
                }
            });

        $this->components->info("{$count} overdue heartbeat(s) marked down");

        return self::SUCCESS;
    }
}
