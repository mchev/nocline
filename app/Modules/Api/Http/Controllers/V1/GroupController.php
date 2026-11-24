<?php

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Requests\ApiGroupRequest;
use App\Modules\Api\Http\Resources\GroupResource;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Group::class);

        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);

        return GroupResource::collection(
            Group::query()->orderBy('position')->orderBy('name')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString(),
        );
    }

    public function store(ApiGroupRequest $request): JsonResponse
    {
        return (new GroupResource(Group::create($request->validated())->refresh()))->response()->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        $this->authorize('viewAny', Group::class);

        return new GroupResource($group);
    }

    public function update(ApiGroupRequest $request, Group $group): GroupResource
    {
        $group->update($request->validated());

        return new GroupResource($group);
    }

    public function destroy(Group $group): Response
    {
        $this->authorize('delete', $group);

        $group->dissolve();

        return response()->noContent();
    }
}
