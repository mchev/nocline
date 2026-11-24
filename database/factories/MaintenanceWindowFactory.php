<?php

namespace Database\Factories;

use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWindow>
 */
class MaintenanceWindowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Planned maintenance',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'recurrence' => MaintenanceRecurrence::None,
        ];
    }

    public function recurring(MaintenanceRecurrence $recurrence): static
    {
        return $this->state(fn () => ['recurrence' => $recurrence]);
    }
}
