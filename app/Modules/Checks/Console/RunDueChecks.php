<?php

namespace App\Modules\Checks\Console;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckExecutor;
use App\Modules\Checks\Services\CheckResultRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:checks:run-due')]
#[Description('Execute hub-local checks (http, tcp, agent) whose next_run_at is due')]
class RunDueChecks extends Command
{
    public function handle(CheckExecutor $executor, CheckResultRecorder $recorder): int
    {
        $count = 0;

        Check::query()
            ->where('enabled', true)
            ->whereHas('state', fn ($q) => $q->where('next_run_at', '<=', now())->orWhereNull('next_run_at'))
            ->with(['host', 'state'])
            ->each(function (Check $check) use ($executor, $recorder, &$count): void {
                if ($check->runsOnProbes()) {
                    return;
                }

                $result = $executor->execute($check);

                if ($result === null) {
                    return;
                }

                $recorder->record($check, $result['ok'], $result['latency_ms'], $result['error'], $result['metadata']);
                $count++;
            });

        $this->components->info("{$count} check(s) executed");

        return self::SUCCESS;
    }
}
