<?php

namespace App\Modules\Api\Models;

use App\Models\User;
use App\Modules\Api\Enums\ApiTokenAbility;
use App\Modules\Api\Policies\ApiTokenPolicy;
use Carbon\CarbonInterface;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A personal API token acting as its owner. Only the sha256 of the secret is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property list<string> $abilities
 * @property CarbonInterface|null $last_used_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'abilities', 'expires_at'])]
#[Hidden(['token_hash'])]
#[UseFactory(ApiTokenFactory::class)]
#[UsePolicy(ApiTokenPolicy::class)]
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    public const PREFIX = 'phr_';

    public const SECRET_LENGTH = 40;

    /** Minimum delay between two `last_used_at` writes, to avoid one write per request. */
    public const TOUCH_INTERVAL_SECONDS = 60;

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Create a token for a user; the plaintext secret is returned once and never stored.
     *
     * @param  list<ApiTokenAbility>  $abilities
     * @return array{token: self, secret: string}
     */
    public static function issue(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt = null): array
    {
        $secret = self::PREFIX.Str::random(self::SECRET_LENGTH);

        $token = new self([
            'name' => $name,
            'abilities' => array_map(fn (ApiTokenAbility $a) => $a->value, $abilities),
            'expires_at' => $expiresAt,
        ]);
        $token->user_id = $user->id;
        $token->token_hash = self::hashSecret($secret);
        $token->save();

        return ['token' => $token, 'secret' => $secret];
    }

    /**
     * Look a token up by its plaintext secret: an indexed lookup on the hash, then a constant-time comparison.
     */
    public static function findBySecret(?string $secret): ?self
    {
        if ($secret === null || ! str_starts_with($secret, self::PREFIX) || strlen($secret) !== strlen(self::PREFIX) + self::SECRET_LENGTH) {
            return null;
        }

        $hash = self::hashSecret($secret);
        $token = static::query()->with('user')->where('token_hash', $hash)->first();

        return $token !== null && hash_equals($token->token_hash, $hash) ? $token : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function can(ApiTokenAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities, true);
    }

    /**
     * Record usage at most once per minute, without touching `updated_at`.
     */
    public function touchLastUsed(): void
    {
        if ($this->last_used_at !== null && $this->last_used_at->gt(now()->subSeconds(self::TOUCH_INTERVAL_SECONDS))) {
            return;
        }

        static::query()->whereKey($this->id)->toBase()->update(['last_used_at' => now()]);
        $this->last_used_at = now();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
