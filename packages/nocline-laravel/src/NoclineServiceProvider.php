<?php

namespace Nocline\Laravel;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Nocline\Laravel\Console\InstallCommand;
use Nocline\Laravel\Console\PingCommand;
use Psr\Log\LoggerInterface;

class NoclineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nocline.php', 'nocline');

        $this->app->singleton(NoclineClient::class, fn (Application $app) => new NoclineClient(
            $app->make(HttpFactory::class),
            $app->make(LoggerInterface::class),
            $app['config']->get('nocline.url'),
            $app['config']->get('nocline.heartbeat_token'),
            (array) $app['config']->get('nocline.heartbeats', []),
            (int) $app['config']->get('nocline.timeout', 3),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/nocline.php' => config_path('nocline.php')], 'nocline-config');

        if ($this->app->runningInConsole()) {
            $this->commands([PingCommand::class, InstallCommand::class]);
        }

        self::registerSchedulerMacros();
    }

    /**
     * Scheduler hooks. They run inside the scheduler process (no queue needed) and, unlike the framework's
     * pingBefore()/thenPing() which wait up to 30 s and report errors, they use NoclineClient's short timeout
     * and swallow every error. `$token` is a token or a name from config('nocline.heartbeats'); null uses the default.
     */
    public static function registerSchedulerMacros(): void
    {
        $nocline = fn () => app(NoclineClient::class);

        Event::macro('pingNoclineBefore', function (?string $token = null) use ($nocline) {
            /** @var Event $this */
            return $this->before(fn () => $nocline()->heartbeat($token));
        });

        Event::macro('pingNoclineOnSuccess', function (?string $token = null) use ($nocline) {
            /** @var Event $this */
            return $this->onSuccess(fn () => $nocline()->heartbeat($token));
        });

        Event::macro('pingNocline', function (?string $token = null) {
            /** @var Event $this */
            return $this->pingNoclineOnSuccess($token);
        });

        Event::macro('thenPingNocline', function (?string $token = null) use ($nocline) {
            /** @var Event $this */
            return $this->then(fn () => $nocline()->heartbeat($token));
        });

        Event::macro('pingNoclineOnFailure', function (?string $token = null) use ($nocline) {
            /** @var Event $this */
            return $this->onFailure(fn () => $nocline()->fail($token));
        });
    }
}
