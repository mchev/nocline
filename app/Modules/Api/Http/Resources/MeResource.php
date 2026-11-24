<?php

namespace App\Modules\Api\Http\Resources;

use App\Models\User;
use App\Modules\Api\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The user behind the token, plus what the token itself may do.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiToken $token */
        $token = $request->attributes->get('api_token');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'token' => [
                'name' => $token->name,
                'abilities' => $token->abilities,
                'expires_at' => $token->expires_at?->toIso8601String(),
            ],
        ];
    }
}
