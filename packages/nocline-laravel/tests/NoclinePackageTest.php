<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Nocline\Laravel\Facades\Nocline;
use Nocline\Laravel\NoclineClient;
use Nocline\Laravel\NoclineServiceProvider;
use Psr\Log\AbstractLogger;
use Tests\TestCase;

require_once __DIR__.'/bootstrap.php';

uses(TestCase::class);

const NOCLINE_TEST_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN';

/**
 * @return array{0: NoclineClient, 1: object{lines: list<string>}}
 */
function noclinePair(?string $url = 'https://nocline.test/', ?string $default = NOCLINE_TEST_TOKEN, array $named = []): array
{
    $logger = new class extends AbstractLogger
    {
        /** @var list<string> */
        public array $lines = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->lines[] = (string) $message;
        }
    };

    return [new NoclineClient(new HttpFactory, $logger, $url, $default, $named), $logger];
}

function noclineBoot(): void
{
    config()->set('nocline.url', 'https://nocline.test');
    config()->set('nocline.heartbeat_token', NOCLINE_TEST_TOKEN);
    config()->set('nocline.heartbeats', ['backup' => 'backuptoken']);
    app()->register(NoclineServiceProvider::class);
}

it('builds the ping address from a token, a configured name or the default', function () {
    [$nocline] = noclinePair(named: ['backup' => 'backuptoken']);

    expect($nocline->urlFor())->toBe('https://nocline.test/api/v1/heartbeat/'.NOCLINE_TEST_TOKEN)
        ->and($nocline->urlFor('other'))->toBe('https://nocline.test/api/v1/heartbeat/other')
        ->and($nocline->urlFor('backup', fail: true))->toBe('https://nocline.test/api/v1/heartbeat/backuptoken/fail');
});

it('returns null and does nothing when nothing is configured', function () {
    Http::fake();
    [$nocline, $logger] = noclinePair(url: null);
    [$noToken] = noclinePair(default: null);

    expect($nocline->urlFor())->toBeNull()
        ->and($nocline->heartbeat())->toBeFalse()
        ->and($noToken->heartbeat())->toBeFalse()
        ->and($logger->lines)->not->toBeEmpty();
    Http::assertNothingSent();
});

it('sends a GET heartbeat and reports whether the hub acknowledged it', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('ok')]);
    $nocline = new NoclineClient($http, noclinePair()[1], 'https://nocline.test', NOCLINE_TEST_TOKEN);

    expect($nocline->heartbeat())->toBeTrue();
    $http->assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://nocline.test/api/v1/heartbeat/'.NOCLINE_TEST_TOKEN);
});

it('reports failures on /fail with the message cut to 200 characters', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('ok')]);
    $nocline = new NoclineClient($http, noclinePair()[1], 'https://nocline.test', NOCLINE_TEST_TOKEN);

    $nocline->fail(message: str_repeat('é', 300));

    $http->assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

        return str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/'.NOCLINE_TEST_TOKEN.'/fail') && mb_strlen($query['msg']) === 200;
    });
});

it('never throws when the hub is down, and keeps the token out of the logs', function () {
    $http = new HttpFactory;
    $http->fake(fn () => throw new ConnectionException('cURL error 28: timeout for https://nocline.test/api/v1/heartbeat/'.NOCLINE_TEST_TOKEN));
    [, $logger] = noclinePair();
    $nocline = new NoclineClient($http, $logger, 'https://nocline.test', NOCLINE_TEST_TOKEN);

    expect($nocline->heartbeat())->toBeFalse()->and($nocline->fail())->toBeFalse()
        ->and(implode(' ', $logger->lines))->not->toContain(NOCLINE_TEST_TOKEN)->toContain('Nocline ping failed');
});

it('treats an unknown token (404) or a server error as not delivered', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('', 404)]);
    $nocline = new NoclineClient($http, noclinePair()[1], 'https://nocline.test', NOCLINE_TEST_TOKEN);

    expect($nocline->heartbeat())->toBeFalse();
});

it('registers the scheduler macros and pings on success only', function () {
    Http::fake(['*' => Http::response('ok')]);
    noclineBoot();
    $schedule = app(Schedule::class);

    $event = $schedule->exec('exit 0')->daily()->pingNoclineOnSuccess('backup')->pingNoclineOnFailure('backup');
    $event->run(app());

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://nocline.test/api/v1/heartbeat/backuptoken');
});

it('pings /fail when the scheduled command fails', function () {
    Http::fake(['*' => Http::response('ok')]);
    noclineBoot();

    $event = app(Schedule::class)->exec('exit 1')->daily()->pingNoclineOnSuccess()->pingNoclineOnFailure();
    try {
        $event->run(app());
    } catch (Throwable) {
        // the framework may rethrow the failure; only the pings matter here
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/'.NOCLINE_TEST_TOKEN.'/fail'));
});

it('supports pingNocline, thenPingNocline and pingNoclineBefore', function () {
    Http::fake(['*' => Http::response('ok')]);
    noclineBoot();

    app(Schedule::class)->exec('exit 0')->daily()->pingNoclineBefore()->thenPingNocline('backup')->pingNocline()->run(app());

    Http::assertSentCount(3);
});

it('does not break the scheduler when the hub is down', function () {
    Http::fake(fn () => throw new ConnectionException('down'));
    noclineBoot();

    $event = app(Schedule::class)->exec('exit 0')->daily()->pingNoclineBefore()->pingNoclineOnSuccess()->thenPingNocline();

    expect(fn () => $event->run(app()))->not->toThrow(Throwable::class);
});

it('exposes the facade for jobs', function () {
    Http::fake(['*' => Http::response('ok')]);
    noclineBoot();

    expect(Nocline::heartbeat('backup'))->toBeTrue();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/heartbeat/backuptoken'));
});

it('provides the nocline:ping command', function () {
    Http::fake(['*' => Http::response('ok')]);
    noclineBoot();

    $this->artisan('nocline:ping', ['token' => 'backup', '--fail' => true, '--message' => 'disk full'])->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/backuptoken/fail') && str_contains($r->url(), 'msg=disk'));
});

it('makes nocline:ping fail visibly when the ping is not delivered', function () {
    Http::fake(['*' => Http::response('', 404)]);
    noclineBoot();

    $this->artisan('nocline:ping')->assertFailed();
});

it('prints the lines to add with nocline:heartbeat:install', function () {
    noclineBoot();

    $this->artisan('nocline:heartbeat:install', ['token' => 'mytoken'])
        ->expectsOutputToContain('NOCLINE_HEARTBEAT_TOKEN=mytoken')
        ->expectsOutputToContain('pingNoclineOnFailure')
        ->assertSuccessful();
});

it('ships a valid composer manifest with auto-discovery', function () {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

    expect($composer['name'])->toBe('nocline/laravel')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Nocline\\Laravel\\')
        ->and($composer['extra']['laravel']['providers'])->toBe([NoclineServiceProvider::class])
        ->and($composer['require']['php'])->toBe('^8.2');
});
