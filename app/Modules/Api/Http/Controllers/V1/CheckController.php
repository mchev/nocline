<?php

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Requests\ApiCheckRequest;
use App\Modules\Api\Http\Resources\CheckResource;
use App\Modules\Api\Http\Resources\CheckStatusResource;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CheckController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Check::class);

        $filters = $request->validate([
            'group_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(CheckType::class)],
            'enabled' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $checks = Check::query()
            ->with('state')
            ->when(isset($filters['group_id']), fn ($q) => $q->where('group_id', $filters['group_id']))
            ->when(isset($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['enabled']), fn ($q) => $q->where('enabled', $request->boolean('enabled')))
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return CheckResource::collection($checks);
    }

    public function store(ApiCheckRequest $request): JsonResponse
    {
        $check = Check::create($request->checkData());

        return (new CheckResource($check->refresh()->load('state')))->response()->setStatusCode(201);
    }

    public function show(Check $check): CheckResource
    {
        $this->authorize('view', $check);

        return new CheckResource($check->load('state'));
    }

    public function update(ApiCheckRequest $request, Check $check): CheckResource
    {
        $check->update($request->checkData());

        return new CheckResource($check->load('state'));
    }

    public function destroy(Check $check): Response
    {
        $this->authorize('delete', $check);

        $check->delete();

        return response()->noContent();
    }

    public function status(Check $check): CheckStatusResource
    {
        $this->authorize('view', $check);

        return new CheckStatusResource($check->load('state'));
    }
}
