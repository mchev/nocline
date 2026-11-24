<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Models\Check;

/**
 * Runs a single hub-local check once (HTTP, TCP, agent presence).
 */
class RunHubCheck
{
    public function __construct(
        private readonly CheckExecutor $executor,
        private readonly CheckResultRecorder $recorder,
    ) {}

    public function run(Check $check): bool
    {
        $check->loadMissing(['host', 'state']);

        if (! $check->enabled || $check->runsOnProbes()) {
            return false;
        }

        $result = $this->executor->execute($check);

        if ($result === null) {
            return false;
        }

        $this->recorder->record(
            $check,
            $result['ok'],
            $result['latency_ms'],
            $result['error'],
            $result['metadata'],
        );

        return true;
    }
}
