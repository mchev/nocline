<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;
use Tests\Concerns\InteractsWithRouteThrottling;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithRouteThrottling;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'app.url' => 'http://localhost',
        ]);

        Cache::flush();

        $this->withoutVite();

        // Route throttles (throttle:N,1) share IP keys in the cache store. Do not
        // exhaust them across the suite — tests that assert 429 opt in via withRouteThrottling().
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
