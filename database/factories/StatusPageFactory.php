<?php

namespace Database\Factories;

use App\Modules\StatusPages\Models\StatusPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusPage>
 */
class StatusPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->company();

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'show_incidents' => true,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
