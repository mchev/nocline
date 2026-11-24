<?php

namespace App\Modules\Setup\Http\Middleware;

use App\Modules\Setup\Services\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToSetup
{
    public function __construct(private InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->is('setup', 'setup/*');
        $installed = $this->state->isInstalled();

        if (! $installed && ! $isSetupRoute && ! $request->is('up')) {
            return redirect()->route('setup.show');
        }

        if ($installed && $isSetupRoute) {
            // Already installed (e.g. a double submit or a stale tab): go to the app, never expose the wizard.
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(404);
            }

            return $request->user()
                ? redirect()->route('dashboard')
                : redirect()->route('login');
        }

        return $next($request);
    }
}
