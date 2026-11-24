<?php

namespace Database\Factories;

use App\Modules\Hosts\Models\Host;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Host>
 */
class HostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->domainWord().'-'.fake()->numberBetween(1, 99),
            'tags' => [],
        ];
    }

    /**
     * Enrolled host; the plaintext API token is "pha_test".
     */
    public function enrolled(string $token = 'pha_test'): static
    {
        return $this->state(fn () => [
            'api_token_hash' => Host::hashToken($token),
            'enrolled_at' => now(),
        ]);
    }
}
