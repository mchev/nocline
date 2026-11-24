<?php

namespace App\Modules\Checks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Http\Requests\DetectSiteRequest;
use App\Modules\Checks\Services\SiteDetector;
use Illuminate\Http\JsonResponse;

/**
 * "We detect it for you": what is this website, and what is worth watching?
 */
class SiteDetectionController extends Controller
{
    public function __invoke(DetectSiteRequest $request, SiteDetector $detector): JsonResponse
    {
        return response()->json($detector->inspect($request->string('url')->toString())->toResponse());
    }
}
