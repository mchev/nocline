<?php

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Resources\CheckResource;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\MonitorCreator;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Site created, monitor it": the same recipe as the guided "A website" screen.
 */
class WebsiteMonitorController extends Controller
{
    public function __invoke(Request $request, MonitorCreator $monitors): JsonResponse
    {
        $this->authorize('create', Check::class);

        $request->merge(['url' => MonitorCreator::normalizeUrl((string) $request->input('url'))]);
        $data = $request->validate(MonitorCreator::websiteRules());

        if (empty($data['group_id']) && ! empty($data['group_name'])) {
            $this->authorize('create', Group::class);
        }

        $check = $monitors->website($data);

        return (new CheckResource($check->refresh()->load('state')))->response()->setStatusCode(201);
    }
}
