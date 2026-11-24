<?php

namespace Database\Factories;

use App\Modules\Groups\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company()];
    }
}
