<?php

namespace App\Modules\Probes\Http\Middleware;

use App\Modules\Probes\Models\Probe;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProbe
{
    public function handle(Request $request, Closure $next): Response
    {
        $probe = Probe::findByApiToken($request->bearerToken());

        if ($probe === null || ! $probe->enabled) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('probe', $probe);

        return $next($request);
    }
}
