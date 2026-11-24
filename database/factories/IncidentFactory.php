<?php

namespace Database\Factories;

use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'check_id' => Check::factory(),
            'title' => 'Check down',
            'started_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }
}
