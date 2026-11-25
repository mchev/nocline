<?php

use App\Modules\Checks\Support\HttpServiceUnavailable;

it('detects maintenance HTTP from error text and metadata', function () {
    expect(HttpServiceUnavailable::isMaintenanceResponse('HTTP 503', []))->toBeTrue()
        ->and(HttpServiceUnavailable::isMaintenanceResponse('HTTP 502', []))->toBeTrue()
        ->and(HttpServiceUnavailable::isMaintenanceResponse('HTTP 500', []))->toBeFalse()
        ->and(HttpServiceUnavailable::isMaintenanceResponse(null, ['http_status' => 503]))->toBeTrue()
        ->and(HttpServiceUnavailable::isMaintenanceResponse('timeout', ['http_status' => 503]))->toBeTrue();
});
