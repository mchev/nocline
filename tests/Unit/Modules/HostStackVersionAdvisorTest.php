<?php

use App\Modules\Hosts\Services\EndOfLifeCatalog;
use App\Modules\Hosts\Support\HostStackVersionAdvisor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EndOfLifeCatalogFixture;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['nocline.stack_eol_check' => true]);
    EndOfLifeCatalogFixture::seedMinimalCatalog();
});

it('flags end of life php and node versions', function () {
    Carbon::setTestNow('2026-11-26');

    $warnings = app(HostStackVersionAdvisor::class)->warnings([
        'php' => '8.1.26',
        'node' => '18.17.0',
    ]);

    expect($warnings)->toHaveKeys(['php', 'node'])
        ->and($warnings['php'])->toContain('PHP')
        ->and($warnings['node'])->toContain('Node');

    Carbon::setTestNow();
});

it('keeps supported php 8.5 and node 22 without warnings', function () {
    Carbon::setTestNow('2026-11-26');

    $warnings = app(HostStackVersionAdvisor::class)->warnings([
        'php' => '8.5.13',
        'node' => '22.14.0',
        'nginx' => '1.31.9',
    ]);

    expect($warnings)->toBe([]);

    Carbon::setTestNow();
});

it('marks eol redis 7.0 and postgresql 14', function () {
    Carbon::setTestNow('2026-11-26');

    $warnings = app(HostStackVersionAdvisor::class)->warnings([
        'redis' => '7.0.12',
        'postgresql' => '14.10',
    ]);

    expect($warnings)->toHaveKeys(['redis', 'postgresql']);

    Carbon::setTestNow();
});

it('returns no warnings when the catalog cache is empty', function () {
    Cache::forget(EndOfLifeCatalog::CACHE_KEY);

    $warnings = app(HostStackVersionAdvisor::class)->warnings([
        'php' => '8.1.26',
    ]);

    expect($warnings)->toBe([]);
});
