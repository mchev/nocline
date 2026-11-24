<?php

namespace App\Modules\Hosts\Concerns;

use Illuminate\Support\Str;

/**
 * One-time enrollment token -> permanent hashed API token, shared by hosts and probes.
 * Models must define ENROLLMENT_PREFIX and API_PREFIX constants and the matching columns.
 */
trait HasAgentToken
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Issue a fresh one-time enrollment token; returns the plaintext (shown once).
     */
    public function issueEnrollmentToken(int $ttlHours = 24): string
    {
        $token = static::ENROLLMENT_PREFIX.Str::random(40);

        $this->forceFill([
            'enrollment_token_hash' => self::hashToken($token),
            'enrollment_expires_at' => now()->addHours($ttlHours),
        ])->save();

        return $token;
    }

    /**
     * Exchange a valid enrollment token for a permanent API token (plaintext returned once).
     *
     * @return array{model: static, token: string}|null
     */
    public static function enrollWithToken(string $enrollmentToken): ?array
    {
        $model = static::query()
            ->where('enrollment_token_hash', self::hashToken($enrollmentToken))
            ->where('enrollment_expires_at', '>', now())
            ->first();

        if ($model === null) {
            return null;
        }

        $apiToken = static::API_PREFIX.Str::random(48);

        $model->forceFill([
            'api_token_hash' => self::hashToken($apiToken),
            'enrollment_token_hash' => null,
            'enrollment_expires_at' => null,
            'enrolled_at' => now(),
        ])->save();

        return ['model' => $model, 'token' => $apiToken];
    }

    public function revokeApiToken(): void
    {
        $this->forceFill(['api_token_hash' => null, 'enrolled_at' => null])->save();
    }

    public static function findByApiToken(?string $token): ?static
    {
        return $token ? static::query()->where('api_token_hash', self::hashToken($token))->first() : null;
    }
}
