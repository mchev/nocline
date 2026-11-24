<?php

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Resources\MeResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): MeResource
    {
        return new MeResource($request->user());
    }
}
