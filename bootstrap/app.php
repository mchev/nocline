<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Modules\Api\Http\Middleware\AuthenticateApiToken;
use App\Modules\Setup\Http\Middleware\RedirectToSetup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../app/Modules/Metrics/Console',
        __DIR__.'/../app/Modules/Checks/Console',
        __DIR__.'/../app/Modules/Setup/Console',
        __DIR__.'/../app/Modules/Renewals/Console',
        __DIR__.'/../app/Modules/Updates/Console',
        __DIR__.'/../app/Modules/Audit/Console',
        __DIR__.'/../app/Modules/Maintenance/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias(['auth.api-token' => AuthenticateApiToken::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: AuthenticateApiToken::class);

        $middleware->web(prepend: [
            RedirectToSetup::class,
        ], append: [
            SecurityHeaders::class,
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Not found.'], 404)
            : null);
    })->create();
