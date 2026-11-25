# nocline/laravel

Heartbeat client for [Nocline](../../README.md): make your Laravel scheduler, queue jobs or backup scripts report to your Nocline hub. If a task stops reporting (or reports a failure), Nocline opens an incident and alerts you — a "dead man's switch" for scheduled work.

- No queue required: pings are sent inline with a **3 second timeout** and every error is swallowed (logged at `debug`, without the secret token). A hub outage can never break or slow your app.
- Works with Laravel 11, 12 and 13 on PHP 8.2+.

> The package is not published on Packagist yet. See [Publishing](#publishing-to-packagist-later).

## Install

```bash
composer require nocline/laravel
```

(Until it is published, require it from a path repository: `{"type": "path", "url": "../nocline/packages/nocline-laravel"}`.)

In Nocline, add a **scheduled task** (Monitor → Something else → A scheduled task). Nocline shows its ping address:

```
https://your-nocline-hub.example.com/api/v1/heartbeat/<token>
```

Then in your `.env`:

```dotenv
NOCLINE_URL=https://your-nocline-hub.example.com
NOCLINE_HEARTBEAT_TOKEN=<token>
```

`php artisan nocline:heartbeat:install <token>` prints these lines for you. To customise the settings, publish the config: `php artisan vendor:publish --tag=nocline-config`.

## Scheduler

Macros are added to scheduled events. They use Laravel's own `before`, `onSuccess`, `onFailure` and `then` hooks.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run')->daily()
    ->pingNoclineOnSuccess('token-or-name')   // after a successful run
    ->pingNoclineOnFailure('token-or-name');  // after a failed run: calls /fail, opens an incident right away
```

| Macro                                 | When it pings                  |
| ------------------------------------- | ------------------------------ |
| `pingNoclineOnSuccess($token = null)` | after a successful run         |
| `pingNoclineOnFailure($token = null)` | after a failed run, on `/fail` |
| `thenPingNocline($token = null)`      | after the run, whatever result |
| `pingNoclineBefore($token = null)`    | before the run starts          |

`$token` is a heartbeat token, or a name declared in `config('nocline.heartbeats')` (`'backup' => env('NOCLINE_HEARTBEAT_BACKUP')`). When omitted, `NOCLINE_HEARTBEAT_TOKEN` is used.

Why not the framework's `pingBefore()` / `thenPing()`? They wait up to 30 seconds and report network errors to your exception handler. The Nocline macros use a short timeout and never throw.

## Jobs, controllers, anywhere

```php
use Nocline\Laravel\Facades\Nocline;

Nocline::heartbeat();                     // default token
Nocline::heartbeat('backup');             // a named heartbeat or a raw token
Nocline::fail('backup', 'disk is full');  // explicit failure with a message (max 200 characters)
```

Both return `true` when the hub acknowledged the ping and `false` otherwise.

## Command line

```bash
php artisan nocline:ping backup
php artisan nocline:ping backup --fail --message="dump failed"
```

The command exits with a non-zero code when the ping could not be delivered, so you notice a wrong token or URL.

## Tests

The package has no Orchestra Testbench dependency; its Pest tests live in `tests/` and run with the hub's own suite (`php artisan test` from the repository root, "Package" test suite), using `tests/bootstrap.php` as a small PSR-4 autoloader.

## Publishing to Packagist later

1. Move `packages/nocline-laravel/` to its own repository (for instance `nocline/laravel`) with `git subtree split --prefix=packages/nocline-laravel -b nocline-laravel`, keeping `composer.json` at the root.
2. Add a `LICENSE` file and the `license` key to `composer.json` (the package is intentionally license-less for now).
3. Tag a release (`v0.1.0`), submit the repository on packagist.org and enable its GitHub webhook.
4. Add `orchestra/testbench` as a `require-dev` of the standalone repository and move `tests/` to it.
