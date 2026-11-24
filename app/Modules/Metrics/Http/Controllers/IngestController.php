<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Http\Requests\IngestRequest;
use App\Modules\Metrics\Services\MetricIngestor;
use App\Support\OpsBroadcast;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __invoke(IngestRequest $request, MetricIngestor $ingestor): JsonResponse
    {
        $host = $request->attributes->get('host');
        $data = $request->validated();

        $ingestor->ingest($host, $data['metrics']);

        $host->forceFill(array_filter([
            'last_seen_at' => now(),
            'agent_version' => $data['agent_version'] ?? null,
        ]))->save();

        OpsBroadcast::ping();

        return response()->json(['accepted' => count($data['metrics'])], 202);
    }
}
