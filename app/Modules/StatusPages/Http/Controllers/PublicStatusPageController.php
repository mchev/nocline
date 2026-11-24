<?php

namespace App\Modules\StatusPages\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\StatusPages\Models\StatusPage;
use App\Modules\StatusPages\Services\StatusPagePayload;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated pages: they only ever expose monitor names and states.
 */
class PublicStatusPageController extends Controller
{
    public function show(string $slug, StatusPagePayload $payload): Response
    {
        return Inertia::render('status/Show', $payload->forPage($this->enabledPage($slug)));
    }

    public function feed(string $slug, StatusPagePayload $payload): JsonResponse
    {
        return response()->json($payload->forPage($this->enabledPage($slug)));
    }

    private function enabledPage(string $slug): StatusPage
    {
        return StatusPage::query()->where('slug', $slug)->where('enabled', true)->firstOrFail();
    }
}
