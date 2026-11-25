<?php

use App\Modules\Checks\Support\WebsiteMonitorRows;

it('ranks down and unknown before up regardless of maintenance', function () {
    expect(WebsiteMonitorRows::problemRank(true, true, 'down'))->toBe(0)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'down'))->toBe(0)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'degraded'))->toBe(1)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'unknown'))->toBe(2)
        ->and(WebsiteMonitorRows::problemRank(true, false, 'up'))->toBe(3)
        ->and(WebsiteMonitorRows::problemRank(true, true, 'up'))->toBe(4)
        ->and(WebsiteMonitorRows::problemRank(false, false, 'down'))->toBe(5);
});

it('ranks ssl attention before nominal ok', function () {
    expect(WebsiteMonitorRows::sslAttentionRank('expired'))->toBe(0)
        ->and(WebsiteMonitorRows::sslAttentionRank('warning'))->toBe(1)
        ->and(WebsiteMonitorRows::sslAttentionRank('ok'))->toBe(2)
        ->and(WebsiteMonitorRows::sslAttentionRank(null))->toBe(2);
});
