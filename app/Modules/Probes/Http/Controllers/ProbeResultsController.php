<?php

namespace App\Modules\Probes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Probes\Models\CheckProbeState;
use App\Modules\Probes\Models\Probe;
use App\Modules\Probes\Services\QuorumEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProbeResultsController extends Controller
{
    public function __invoke(Request $request, QuorumEvaluator $quorum, CheckResultRecorder $recorder): JsonResponse
    {
        /** @var Probe $probe */
        $probe = $request->attributes->get('probe');

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1', 'max:200'],
            'results.*.check_id' => ['required', 'integer'],
            'results.*.ok' => ['required', 'boolean'],
            'results.*.latency_ms' => ['nullable', 'integer', 'min:0'],
            'results.*.error' => ['nullable', 'string', 'max:500'],
            'results.*.metadata' => ['nullable', 'array'],
        ]);

        $probe->forceFill(['last_seen_at' => now()])->save();

        $accepted = 0;

        foreach ($data['results'] as $result) {
            $check = Check::query()->with('state')->whereKey($result['check_id'])->first();

            if ($check === null || ! $check->enabled || ! $check->runsOnProbes()) {
                continue;
            }

            CheckProbeState::query()->updateOrCreate(
                ['check_id' => $check->id, 'probe_id' => $probe->id],
                [
                    'last_run_at' => now(),
                    'last_ok' => $result['ok'],
                    'last_latency_ms' => $result['latency_ms'] ?? null,
                    'last_error' => $result['ok'] ? null : ($result['error'] ?? null),
                ],
            );
            $accepted++;

            $verdict = $quorum->evaluate($check);

            if ($verdict === null) {
                continue;
            }

            // A burst of probes must count as one cycle: within half an interval only a
            // verdict that contradicts the recorded state is applied.
            $state = $check->state;
            $recent = $state?->last_run_at?->gt(now()->subSeconds((int) ($check->interval_sec / 2))) ?? false;
            $agrees = $verdict['ok'] === ($state->consecutive_failures === 0);

            if ($recent && $agrees) {
                continue;
            }

            $recorder->record($check, $verdict['ok'], $verdict['latency_ms'], $verdict['error'], [
                'probes' => $verdict['probes'],
                'probes_down' => $verdict['down'],
                'quorum' => $check->quorum,
            ]);
        }

        return response()->json(['accepted' => $accepted], 202);
    }
}
