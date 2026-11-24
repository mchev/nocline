<?php

namespace App\Modules\Probes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Probes\Models\CheckProbeState;
use App\Modules\Probes\Models\Probe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProbeWorkController extends Controller
{
    /**
     * Leases the checks that are due for this probe and pushes their next run forward.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Probe $probe */
        $probe = $request->attributes->get('probe');

        $capabilities = array_values(array_intersect(
            explode(',', (string) $request->query('capabilities', 'http,tcp')),
            array_column(CheckType::cases(), 'value'),
        ));

        $probe->forceFill(['last_seen_at' => now(), 'capabilities' => $capabilities])->save();

        $work = [];

        Check::query()
            ->where('enabled', true)
            ->whereIn('type', array_intersect($capabilities, ['http', 'tcp', 'icmp', 'dns']))
            ->where(fn ($q) => $q->where('execution', CheckExecution::Probe)->orWhereIn('type', ['icmp', 'dns']))
            ->each(function (Check $check) use ($probe, &$work): void {
                $state = CheckProbeState::query()->firstOrNew(['check_id' => $check->id, 'probe_id' => $probe->id]);

                if ($state->next_run_at !== null && $state->next_run_at->isFuture()) {
                    return;
                }

                $state->next_run_at = now()->addSeconds($check->interval_sec);
                $state->save();

                $work[] = [
                    'id' => $check->id,
                    'type' => $check->type->value,
                    'target' => $check->target,
                    'timeout_sec' => $check->timeout_sec,
                    'config' => (object) ($check->config ?? []),
                ];
            });

        return response()->json(['checks' => $work]);
    }
}
