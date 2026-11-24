# phare/laravel

Heartbeat client for [Phare](../../README.md): make your Laravel scheduler, queue jobs or backup scripts report to your Phare hub. If a task stops reporting (or reports a failure), Phare opens an incident and alerts you — a "dead man's switch" for scheduled work.

- No queue required: pings are sent inline with a **3 second timeout** and every error is swallowed (logged at `debug`, without the secret token). A hub outage can never break or slow your app.
- Works with Laravel 11, 12 and 13 on PHP 8.2+.

> The package is not published on Packagist yet. See [Publishing](#publishing-to-packagist-later).

## Install

```bash
composer require phare/laravel
```

(Until it is published, require it from a path repository: `{"type": "path", "url": "../phare/packages/phare-laravel"}`.)

In Phare, add a **scheduled task** (Monitor → Something else → A scheduled task). Phare shows its ping address:

```
https://your-phare-hub.example.com/api/v1/heartbeat/<token>
```

Then in your `.env`:

```dotenv
PHARE_URL=https://your-phare-hub.example.com
PHARE_HEARTBEAT_TOKEN=<token>
```

`php artisan phare:heartbeat:install <token>` prints these lines for you. To customise the settings, publish the config: `php artisan vendor:publish --tag=phare-config`.

## Scheduler

Macros are added to scheduled events. They use Laravel's own `before`, `onSuccess`, `onFailure` and `then` hooks.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run')->daily()
    ->pingPhareOnSuccess('token-or-name')   // after a successful run
    ->pingPhareOnFailure('token-or-name');  // after a failed run: calls /fail, opens an incident right away
```

| Macro | When it pings |
|-------|---------------|
| `pingPhare($token = null)` | after a successful run (same as `pingPhareOnSuccess`) |
| `pingPhareOnSuccess($token = null)` | after a successful run |
| `pingPhareOnFailure($token = null)` | after a failed run, on `/fail` |
| `thenPingPhare($token = null)` | after the run, whatever the result |
| `pingPhareBefore($token = null)` | before the run starts |

`$token` is a heartbeat token, or a name declared in `config('phare.heartbeats')` (`'backup' => env('PHARE_HEARTBEAT_BACKUP')`). When omitted, `PHARE_HEARTBEAT_TOKEN` is used.

Why not the framework's `pingBefore()` / `thenPing()`? They wait up to 30 seconds and report network errors to your exception handler. The Phare macros use a short timeout and never throw.

## Jobs, controllers, anywhere

```php
use Phare\Laravel\Facades\Phare;

Phare::heartbeat();                     // default token
Phare::heartbeat('backup');             // a named heartbeat or a raw token
Phare::fail('backup', 'disk is full');  // explicit failure with a message (max 200 characters)
```

Both return `true` when the hub acknowledged the ping and `false` otherwise.

## Command line

```bash
php artisan phare:ping backup
php artisan phare:ping backup --fail --message="dump failed"
```

The command exits with a non-zero code when the ping could not be delivered, so you notice a wrong token or URL.

## Tests

The package has no Orchestra Testbench dependency; its Pest tests live in `tests/` and run with the hub's own suite (`php artisan test` from the repository root, "Package" test suite), using `tests/bootstrap.php` as a small PSR-4 autoloader.

## Publishing to Packagist later

1. Move `packages/phare-laravel/` to its own repository (for instance `phare/laravel`) with `git subtree split --prefix=packages/phare-laravel -b phare-laravel`, keeping `composer.json` at the root.
2. Add a `LICENSE` file and the `license` key to `composer.json` (the package is intentionally license-less for now).
3. Tag a release (`v0.1.0`), submit the repository on packagist.org and enable its GitHub webhook.
4. Add `orchestra/testbench` as a `require-dev` of the standalone repository and move `tests/` to it.
