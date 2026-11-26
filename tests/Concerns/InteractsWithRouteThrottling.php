<?php

namespace Tests\Concerns;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

trait InteractsWithRouteThrottling
{
    protected function withRouteThrottling(): void
    {
        Cache::flush();
        $this->withMiddleware(ThrottleRequests::class);
    }
}
