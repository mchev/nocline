<?php

namespace Phare\Laravel;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Phare\Laravel\Console\InstallCommand;
use Phare\Laravel\Console\PingCommand;
use Psr\Log\LoggerInterface;

class PhareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/phare.php', 'phare');

        $this->app->singleton(PhareClient::class, fn (Application $app) => new PhareClient(
            $app->make(HttpFactory::class),
            $app->make(LoggerInterface::class),
            $app['config']->get('phare.url'),
            $app['config']->get('phare.heartbeat_token'),
            (array) $app['config']->get('phare.heartbeats', []),
            (int) $app['config']->get('phare.timeout', 3),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/phare.php' => config_path('phare.php')], 'phare-config');

        if ($this->app->runningInConsole()) {
            $this->commands([PingCommand::class, InstallCommand::class]);
        }

        self::registerSchedulerMacros();
    }

    /**
     * Scheduler hooks. They run inside the scheduler process (no queue needed) and, unlike the framework's
     * pingBefore()/thenPing() which wait up to 30 s and report errors, they use PhareClient's short timeout
     * and swallow every error. `$token` is a token or a name from config('phare.heartbeats'); null uses the default.
     */
    public static function registerSchedulerMacros(): void
    {
        $phare = fn () => app(PhareClient::class);

        Event::macro('pingPhareBefore', function (?string $token = null) use ($phare) {
            /** @var Event $this */
            return $this->before(fn () => $phare()->heartbeat($token));
        });

        Event::macro('pingPhareOnSuccess', function (?string $token = null) use ($phare) {
            /** @var Event $this */
            return $this->onSuccess(fn () => $phare()->heartbeat($token));
        });

        Event::macro('pingPhare', function (?string $token = null) {
            /** @var Event $this */
            return $this->pingPhareOnSuccess($token);
        });

        Event::macro('thenPingPhare', function (?string $token = null) use ($phare) {
            /** @var Event $this */
            return $this->then(fn () => $phare()->heartbeat($token));
        });

        Event::macro('pingPhareOnFailure', function (?string $token = null) use ($phare) {
            /** @var Event $this */
            return $this->onFailure(fn () => $phare()->fail($token));
        });
    }
}
