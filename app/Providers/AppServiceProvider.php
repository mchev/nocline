<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Alertes\Listeners\SendIncidentAlerts;
use App\Modules\Audit\Listeners\RecordAuthActivity;
use App\Modules\Audit\Observers\AuditObserver;
use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Events\CheckRecovered;
use App\Modules\Checks\Events\CheckWentDown;
use App\Modules\Checks\Jobs\DetectSiteFramework;
use App\Modules\Checks\Jobs\FetchCheckFavicon;
use App\Modules\Checks\Listeners\RunHubCheckOnCreate;
use App\Modules\Checks\Services\SiteFaviconSync;
use App\Modules\Checks\Services\SiteFrameworkSync;
use App\Modules\Incidents\Events\IncidentOpened;
use App\Modules\Incidents\Events\IncidentResolved;
use App\Modules\Incidents\Listeners\OpenIncidentOnCheckDown;
use App\Modules\Incidents\Listeners\ResolveIncidentOnCheckRecovered;
use App\Modules\Renewals\Services\RenewalSync;
use App\Modules\Setup\Services\InstallState;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Before the first setup there may be no database: keep the wizard DB-free.
        if (! app()->runningInConsole() && ! app(InstallState::class)->isInstalled()) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerModuleListeners();
    }

    /**
     * Wire modules together through events only.
     */
    protected function registerModuleListeners(): void
    {
        if ($this->app->environment('local')) {
            Event::listen(Authenticated::class, function (Authenticated $event): void {
                $user = $event->user;

                if (! $user instanceof User || $user->email_verified_at !== null) {
                    return;
                }

                $user->forceFill(['email_verified_at' => now()])->save();
            });
        }

        Event::listen(CheckWentDown::class, OpenIncidentOnCheckDown::class);
        Event::listen(CheckRecovered::class, ResolveIncidentOnCheckRecovered::class);
        Event::listen(CheckCreated::class, fn (CheckCreated $event) => RenewalSync::forCheck($event->check));
        Event::listen(CheckCreated::class, function (CheckCreated $event): void {
            if (app(SiteFaviconSync::class)->appliesTo($event->check)) {
                FetchCheckFavicon::dispatch($event->check->id);
            }
        });
        Event::listen(CheckCreated::class, RunHubCheckOnCreate::class);
        Event::listen(CheckCreated::class, function (CheckCreated $event): void {
            if (app(SiteFrameworkSync::class)->needsSync($event->check)) {
                DetectSiteFramework::dispatch($event->check->id);
            }
        });
        Event::listen([IncidentOpened::class, IncidentResolved::class], SendIncidentAlerts::class);
        Event::subscribe(RecordAuthActivity::class);
        AuditObserver::register();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
