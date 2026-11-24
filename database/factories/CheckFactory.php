<?php

namespace Database\Factories;

use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->domainName(),
            'type' => CheckType::Http,
            'target' => 'https://'.fake()->domainName(),
            'interval_sec' => 60,
            'timeout_sec' => 10,
            'failure_threshold' => 2,
            'enabled' => true,
            'execution' => CheckExecution::Hub,
        ];
    }

    /**
     * A scheduled-task check: push-only, with a generated ping token.
     */
    public function heartbeat(int $intervalSec = 60): static
    {
        return $this->state(fn () => [
            'type' => CheckType::Heartbeat,
            'target' => '',
            'interval_sec' => $intervalSec,
        ]);
    }
}
