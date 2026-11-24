<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;

it('renders check detail with uptime history', function () {
    $user = User::factory()->create();
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://demo.test',
    ]);

    CheckEvent::create([
        'check_id' => $check->id,
        'from_status' => CheckStatus::Unknown,
        'to_status' => CheckStatus::Up,
        'at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('checks.show', $check))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('checks/Show')
            ->where('check.id', $check->id)
            ->where('check.target', 'https://demo.test')
            ->has('recent.timeline')
            ->has('stats')
            ->has('chart.points'));
});

it('returns earlier history as json', function () {
    $user = User::factory()->create();
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'created_at' => now()->subDays(30),
    ]);

    $this->actingAs($user)
        ->getJson(route('checks.history', ['check' => $check, 'hours' => 168]))
        ->assertOk()
        ->assertJsonStructure([
            'timeline',
            'history',
            'window_start',
            'window_end',
            'can_load_earlier',
        ]);
});
