<?php

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Resources\HostResource;
use App\Modules\Hosts\Models\Host;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Host::class);

        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);

        return HostResource::collection(
            Host::query()->orderBy('name')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString(),
        );
    }
}
