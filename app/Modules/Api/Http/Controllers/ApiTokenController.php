<?php

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Enums\ApiTokenAbility;
use App\Modules\Api\Http\Requests\ApiTokenRequest;
use App\Modules\Api\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal API tokens: each user lists, creates and revokes their own.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApiToken::class);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $request->user()->apiTokens()->orderByDesc('id')->get()->map(fn (ApiToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'can_write' => $t->can(ApiTokenAbility::Write),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'expired' => $t->isExpired(),
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
            'new_secret' => $request->session()->get('api_token_secret'),
        ]);
    }

    /**
     * The secret is flashed to the next page load only: it is never stored in clear and cannot be shown again.
     */
    public function store(ApiTokenRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ['secret' => $secret] = ApiToken::issue(
            $request->user(),
            $data['name'],
            $data['access'] === 'write' ? [ApiTokenAbility::Read, ApiTokenAbility::Write] : [ApiTokenAbility::Read],
            isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
        );

        return redirect()->route('api-tokens.index')->with('api_token_secret', $secret);
    }

    public function destroy(ApiToken $token): RedirectResponse
    {
        $this->authorize('delete', $token);

        $token->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
