<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Priority: user preference, session choice (setup / guests), instance default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            config('app.locale'),
        ];

        foreach ($candidates as $candidate) {
            if (Locales::isAvailable($candidate)) {
                app()->setLocale($candidate);
                break;
            }
        }

        return $next($request);
    }
}
