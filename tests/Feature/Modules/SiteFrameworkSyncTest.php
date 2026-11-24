<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteDetector;
use App\Modules\Checks\Services\SiteFrameworkSync;
use App\Modules\Checks\Services\SiteInspection;
use App\Modules\Checks\Support\SiteFrameworkTag;

it('tags laravel when detection succeeds', function () {
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://acme.test',
        'tags' => null,
    ]);

    $this->mock(SiteDetector::class, function ($mock): void {
        $mock->shouldReceive('inspect')
            ->once()
            ->andReturn(new SiteInspection(
                reachable: true,
                finalUrl: 'https://acme.test',
                statusCode: 200,
                framework: SiteFramework::Laravel,
            ));
    });

    expect(app(SiteFrameworkSync::class)->sync($check))->toBeTrue();
    expect(SiteFrameworkTag::fromCheck($check->refresh()))->toBe(SiteFramework::Laravel);
});

it('exposes site_framework on dashboard overview', function () {
    $user = User::factory()->create();
    $site = Check::factory()->create([
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
        'tags' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('overview.sites', 1)
            ->where('overview.sites.0.site_framework', 'laravel'));
});
