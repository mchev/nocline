<?php

use App\Modules\Checks\Support\WebsiteMonitorRows;

it('ranks down and maintenance before healthy sites', function () {
    expect(WebsiteMonitorRows::problemRank(true, true, 'down'))->toBe(0)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'down'))->toBe(0)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'degraded'))->toBe(1)
        ->and(WebsiteMonitorRows::problemRank(true, true, 'up'))->toBe(2)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'unknown'))->toBe(3)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'up'))->toBe(4)
        ->and(WebsiteMonitorRows::problemRank(false, false, 'down'))->toBe(5);
});

it('sorts rows with problems before healthy sites', function () {
    $rows = WebsiteMonitorRows::sortRows([
        ['name' => 'Zebra up', 'enabled' => true, 'in_maintenance' => false, 'status' => 'up', 'ssl_status' => 'ok'],
        ['name' => 'Alpha down', 'enabled' => true, 'in_maintenance' => false, 'status' => 'down', 'ssl_status' => 'ok'],
        ['name' => 'Mike degraded', 'enabled' => true, 'in_maintenance' => false, 'status' => 'degraded', 'ssl_status' => 'ok'],
    ]);

    expect(array_column($rows, 'name'))->toBe(['Alpha down', 'Mike degraded', 'Zebra up']);
});

it('ranks ssl attention before nominal ok', function () {
    expect(WebsiteMonitorRows::sslAttentionRank('expired'))->toBe(0)
        ->and(WebsiteMonitorRows::sslAttentionRank('warning'))->toBe(1)
        ->and(WebsiteMonitorRows::sslAttentionRank('ok'))->toBe(2)
        ->and(WebsiteMonitorRows::sslAttentionRank(null))->toBe(2);
});

it('lists planned maintenance before healthy sites when the monitor is still up', function () {
    $rows = WebsiteMonitorRows::sortRows([
        ['name' => 'AAA up', 'enabled' => true, 'in_maintenance' => false, 'status' => 'up', 'ssl_status' => 'ok'],
        ['name' => 'ZZZ maintenance up', 'enabled' => true, 'in_maintenance' => true, 'status' => 'up', 'ssl_status' => 'ok'],
    ]);

    expect(array_column($rows, 'name'))->toBe(['ZZZ maintenance up', 'AAA up']);
});

it('lists down sites before up sites even when down is in maintenance', function () {
    $rows = WebsiteMonitorRows::sortRows([
        ['name' => 'AAA up', 'enabled' => true, 'in_maintenance' => false, 'status' => 'up', 'ssl_status' => 'ok'],
        ['name' => 'ZZZ down maintenance', 'enabled' => true, 'in_maintenance' => true, 'status' => 'down', 'ssl_status' => 'ok'],
    ]);

    expect(array_column($rows, 'name'))->toBe(['ZZZ down maintenance', 'AAA up']);
});
