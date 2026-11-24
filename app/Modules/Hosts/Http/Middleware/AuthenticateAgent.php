<?php

namespace App\Modules\Hosts\Http\Middleware;

use App\Modules\Hosts\Models\Host;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = Host::findByApiToken($request->bearerToken());

        if ($host === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('host', $host);

        return $next($request);
    }
}
