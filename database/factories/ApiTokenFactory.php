<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Api\Enums\ApiTokenAbility;
use App\Modules\Api\Models\ApiToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /** Plaintext secret of factory-made tokens ("phr_" + 40 "a"). */
    public const SECRET = 'phr_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /**
     * Read-only token whose plaintext secret is self::SECRET.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token_hash' => ApiToken::hashSecret(self::SECRET),
            'abilities' => [ApiTokenAbility::Read->value],
        ];
    }

    public function writable(): static
    {
        return $this->state(fn () => ['abilities' => [ApiTokenAbility::Read->value, ApiTokenAbility::Write->value]]);
    }

    /**
     * Use a distinct plaintext secret (several tokens in one test).
     */
    public function withSecret(string $secret): static
    {
        return $this->state(fn () => ['token_hash' => ApiToken::hashSecret($secret)]);
    }
}
