<?php

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\WebsiteSslStatus;
use App\Modules\Renewals\Models\Renewal;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('classifies ssl renewal state for https website checks', function () {
    $check = Check::factory()->make([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://example.com',
    ]);

    expect(WebsiteSslStatus::forCheck($check, null))->toBe('unknown');

    $ok = new Renewal([
        'kind' => 'ssl',
        'expires_on' => CarbonImmutable::now()->addDays(40),
        'check_error' => null,
    ]);

    expect(WebsiteSslStatus::forCheck($check, $ok))->toBe('ok');

    $warn = new Renewal([
        'kind' => 'ssl',
        'expires_on' => CarbonImmutable::now()->addDays(10),
        'check_error' => null,
    ]);

    expect(WebsiteSslStatus::forCheck($check, $warn))->toBe('warning');

    expect(WebsiteSslStatus::forCheck($check, new Renewal([
        'kind' => 'ssl',
        'expires_on' => CarbonImmutable::now()->subDay(),
        'check_error' => null,
    ])))->toBe('expired');

    $failed = new Renewal;
    $failed->forceFill([
        'kind' => 'ssl',
        'expires_on' => CarbonImmutable::now()->addDays(5),
        'check_error' => 'Certificate unreadable',
    ]);

    expect(WebsiteSslStatus::forCheck($check, $failed))->toBe('error');

    $http = Check::factory()->make([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'http://example.com',
    ]);

    expect(WebsiteSslStatus::forCheck($http, $ok))->toBeNull();
});
