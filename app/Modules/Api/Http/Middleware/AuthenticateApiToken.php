<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Enums\ApiTokenAbility;
use App\Modules\Api\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates `Authorization: Bearer phr_…` as the token's owner. The token's abilities are applied
 * on top of the owner's role: GET/HEAD need `read`, anything else needs `write`, and policies still decide.
 */
class AuthenticateApiToken
{
    /** Requests per minute and per token. */
    public const RATE_LIMIT = 120;

    /** Failed authentications per minute and per IP address, so secrets cannot be brute-forced. */
    public const FAILURE_LIMIT = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $failureKey = 'api-token-failures:'.$request->ip();

        $failureLimit = max(1, (int) config('nocline.api_token_failure_limit', self::FAILURE_LIMIT));

        if (RateLimiter::tooManyAttempts($failureKey, $failureLimit)) {
            return $this->tooManyRequests(RateLimiter::availableIn($failureKey));
        }

        $token = ApiToken::findBySecret($request->bearerToken());

        if ($token === null || $token->isExpired() || $token->user === null) {
            RateLimiter::hit($failureKey);

            return response()->json(['message' => 'Unauthenticated.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $limitKey = 'api-token:'.$token->id;

        $rateLimit = max(1, (int) config('nocline.api_token_rate_limit', self::RATE_LIMIT));

        if (RateLimiter::tooManyAttempts($limitKey, $rateLimit)) {
            return $this->tooManyRequests(RateLimiter::availableIn($limitKey));
        }

        RateLimiter::hit($limitKey);

        $needed = $request->isMethodSafe() ? ApiTokenAbility::Read : ApiTokenAbility::Write;

        if (! $token->can($needed)) {
            return response()->json(['message' => "This token does not have the \"{$needed->value}\" ability."], 403);
        }

        $token->touchLastUsed();
        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($limitKey, $rateLimit));

        return $response;
    }

    private function tooManyRequests(int $retryAfter): JsonResponse
    {
        return response()->json(['message' => 'Too many requests.'], 429, ['Retry-After' => $retryAfter]);
    }
}
