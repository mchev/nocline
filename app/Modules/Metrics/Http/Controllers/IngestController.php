<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hosts\Services\AgentUpdateOffer;
use App\Modules\Hosts\Services\HostMetadataApplier;
use App\Modules\Metrics\Http\Requests\IngestRequest;
use App\Modules\Metrics\Services\HostMetricAlertEvaluator;
use App\Modules\Metrics\Services\MetricIngestor;
use App\Support\OpsBroadcast;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __invoke(
        IngestRequest $request,
        MetricIngestor $ingestor,
        HostMetadataApplier $metadata,
        AgentUpdateOffer $agentUpdate,
        HostMetricAlertEvaluator $metricAlerts,
    ): JsonResponse {
        $host = $request->attributes->get('host');
        $data = $request->validated();

        $ingestor->ingest($host, $data['metrics']);
        $metricAlerts->evaluate($host);

        if (! empty($data['host_meta']) && is_array($data['host_meta'])) {
            $metadata->apply($host, $data['host_meta']);
        }

        $host->forceFill(array_filter([
            'last_seen_at' => now(),
            'agent_version' => $data['agent_version'] ?? null,
        ]))->save();

        OpsBroadcast::ping();

        $payload = ['accepted' => count($data['metrics'])];

        $offer = $agentUpdate->forHost($host, $data['agent_version'] ?? null);
        if ($offer !== null) {
            $payload['agent_update'] = $offer;
        }

        return response()->json($payload, 202);
    }
}
