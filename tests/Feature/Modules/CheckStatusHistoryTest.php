<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Services\CheckStatusHistory;
use App\Modules\Maintenance\Services\MaintenanceChecker;

it('builds 24h history dots from status transitions', function () {
    $check = Check::factory()->create(['created_at' => now()->subDays(2)]);
    $check->state()->update(['status' => CheckStatus::Up]);

    CheckEvent::create([
        'check_id' => $check->id,
        'from_status' => CheckStatus::Up,
        'to_status' => CheckStatus::Down,
        'at' => now()->subHours(6),
    ]);
    CheckEvent::create([
        'check_id' => $check->id,
        'from_status' => CheckStatus::Down,
        'to_status' => CheckStatus::Up,
        'at' => now()->subHours(5),
    ]);

    $checks = Check::query()->whereKey($check->id)->with('state')->get();

    $history = app(CheckStatusHistory::class)->forChecks($checks, app(MaintenanceChecker::class));

    expect($history)->toHaveKey($check->id)
        ->and($history[$check->id]['history'])->toHaveCount(CheckStatusHistory::BUCKETS)
        ->and($history[$check->id]['history'])->toContain('down')
        ->and($history[$check->id]['uptime_24h_pct'])->toBeLessThan(100);
});

it('exposes history on the checks index', function () {
    $user = User::factory()->create();
    $check = Check::factory()->create(['created_at' => now()->subDay()]);
    $check->state()->update(['status' => CheckStatus::Up]);

    $this->actingAs($user)
        ->get(route('checks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('checks/Index')
            ->has('checks', 1)
            ->where('checks.0.id', $check->id)
            ->has('checks.0.history', CheckStatusHistory::BUCKETS)
            ->where('checks.0.uptime_24h_pct', 100));
});
