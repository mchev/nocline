<?php

namespace App\Modules\Hosts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Services\HostMetadataApplier;
use App\Modules\Hosts\Services\HostRebootAlertEvaluator;
use App\Modules\Hosts\Support\HostMetadataRules;
use App\Modules\Probes\Models\Probe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollController extends Controller
{
    /**
     * Exchange a one-time enrollment token (host or probe) for a permanent agent token.
     */
    public function __invoke(Request $request, HostMetadataApplier $metadata, HostRebootAlertEvaluator $rebootAlerts): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'agent_version' => ['nullable', 'string', 'max:32'],
            'os' => ['nullable', 'string', 'max:64'],
            ...HostMetadataRules::rules(),
        ]);

        $isProbe = str_starts_with($data['token'], Probe::ENROLLMENT_PREFIX);
        $result = $isProbe ? Probe::enrollWithToken($data['token']) : Host::enrollWithToken($data['token']);

        if ($result === null) {
            return response()->json(['message' => 'Invalid or expired enrollment token.'], 422);
        }

        $model = $result['model'];
        $model->forceFill(['agent_version' => $data['agent_version'] ?? null]);

        if (! $isProbe) {
            $model->forceFill(['os' => $data['os'] ?? null]);

            if ($model instanceof Host && ! empty($data['host_meta']) && is_array($data['host_meta'])) {
                $previousMeta = $model->agent_meta;
                $metadata->apply($model, $data['host_meta']);
                $rebootAlerts->afterMetadata($model, $previousMeta);
            }
        }

        $model->save();

        return response()->json([
            'host_id' => $model->id,
            'role' => $isProbe ? 'probe' : 'host',
            'token' => $result['token'],
        ], 201);
    }
}
