<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conservative browser hardening headers for the web group.
 *
 * Wallboards (shown on TVs / embedded in dashboards) and public status pages (meant to be embedded)
 * may be framed; everything else, the admin UI included, may only be framed by its own origin.
 * No Content-Security-Policy on purpose: see docs/security.md.
 */
class SecurityHeaders
{
    /** Paths that may be shown inside a frame on another site. */
    private const FRAMEABLE_PATHS = ['wallboard/*', 'status/*'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if (! $request->is(...self::FRAMEABLE_PATHS)) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
