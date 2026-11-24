<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\DetectedSiteChecks;
use App\Modules\Checks\Support\SiteHealthCompanion;

it('links laravel site and health checks in config', function () {
    $site = Check::factory()->create([
        'name' => 'ouvrage.art',
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://ouvrage.art',
    ]);

    $health = app(DetectedSiteChecks::class)->createHealthCheck($site);

    $site->refresh();

    expect($site->config['health_check_id'])->toBe($health->id);
    expect($health->config['companion_of'])->toBe($site->id);
    expect(SiteHealthCompanion::looksLikeHealthCompanion($health))->toBeTrue();
    expect(SiteHealthCompanion::linkMeta($site))->toHaveKey('health_check_id', $health->id);
    expect(SiteHealthCompanion::linkMeta($health))->toMatchArray([
        'companion_of' => $site->id,
        'is_health_companion' => true,
    ]);
});

it('exposes health link meta on dashboard site rows', function () {
    $user = User::factory()->create();
    $site = Check::factory()->create([
        'name' => 'aaa.demo',
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://aaa.demo',
    ]);
    $health = app(DetectedSiteChecks::class)->createHealthCheck($site);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn ($page) => $page->has('overview.sites', 1));

    $sites = collect($response->viewData('page')['props']['overview']['sites']);
    $siteRow = $sites->firstWhere('id', $site->id);

    expect($siteRow)->not->toBeNull();
    expect($siteRow['health_check_id'])->toBe($health->id);
    expect($sites->firstWhere('id', $health->id))->toBeNull();
});
