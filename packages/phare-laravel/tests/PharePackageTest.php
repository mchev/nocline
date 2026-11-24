<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Phare\Laravel\Facades\Phare;
use Phare\Laravel\PhareClient;
use Phare\Laravel\PhareServiceProvider;
use Psr\Log\AbstractLogger;
use Tests\TestCase;

require_once __DIR__.'/bootstrap.php';

uses(TestCase::class);

const PHARE_TEST_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN';

/**
 * @return array{0: PhareClient, 1: object{lines: list<string>}}
 */
function pharePair(?string $url = 'https://phare.test/', ?string $default = PHARE_TEST_TOKEN, array $named = []): array
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

    return [new PhareClient(new HttpFactory, $logger, $url, $default, $named), $logger];
}

function phareBoot(): void
{
    config()->set('phare.url', 'https://phare.test');
    config()->set('phare.heartbeat_token', PHARE_TEST_TOKEN);
    config()->set('phare.heartbeats', ['backup' => 'backuptoken']);
    app()->register(PhareServiceProvider::class);
}

it('builds the ping address from a token, a configured name or the default', function () {
    [$phare] = pharePair(named: ['backup' => 'backuptoken']);

    expect($phare->urlFor())->toBe('https://phare.test/api/v1/heartbeat/'.PHARE_TEST_TOKEN)
        ->and($phare->urlFor('other'))->toBe('https://phare.test/api/v1/heartbeat/other')
        ->and($phare->urlFor('backup', fail: true))->toBe('https://phare.test/api/v1/heartbeat/backuptoken/fail');
});

it('returns null and does nothing when nothing is configured', function () {
    Http::fake();
    [$phare, $logger] = pharePair(url: null);
    [$noToken] = pharePair(default: null);

    expect($phare->urlFor())->toBeNull()
        ->and($phare->heartbeat())->toBeFalse()
        ->and($noToken->heartbeat())->toBeFalse()
        ->and($logger->lines)->not->toBeEmpty();
    Http::assertNothingSent();
});

it('sends a GET heartbeat and reports whether the hub acknowledged it', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('ok')]);
    $phare = new PhareClient($http, pharePair()[1], 'https://phare.test', PHARE_TEST_TOKEN);

    expect($phare->heartbeat())->toBeTrue();
    $http->assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://phare.test/api/v1/heartbeat/'.PHARE_TEST_TOKEN);
});

it('reports failures on /fail with the message cut to 200 characters', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('ok')]);
    $phare = new PhareClient($http, pharePair()[1], 'https://phare.test', PHARE_TEST_TOKEN);

    $phare->fail(message: str_repeat('é', 300));

    $http->assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

        return str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/'.PHARE_TEST_TOKEN.'/fail') && mb_strlen($query['msg']) === 200;
    });
});

it('never throws when the hub is down, and keeps the token out of the logs', function () {
    $http = new HttpFactory;
    $http->fake(fn () => throw new ConnectionException('cURL error 28: timeout for https://phare.test/api/v1/heartbeat/'.PHARE_TEST_TOKEN));
    [, $logger] = pharePair();
    $phare = new PhareClient($http, $logger, 'https://phare.test', PHARE_TEST_TOKEN);

    expect($phare->heartbeat())->toBeFalse()->and($phare->fail())->toBeFalse()
        ->and(implode(' ', $logger->lines))->not->toContain(PHARE_TEST_TOKEN)->toContain('Phare ping failed');
});

it('treats an unknown token (404) or a server error as not delivered', function () {
    $http = new HttpFactory;
    $http->fake(['*' => $http->response('', 404)]);
    $phare = new PhareClient($http, pharePair()[1], 'https://phare.test', PHARE_TEST_TOKEN);

    expect($phare->heartbeat())->toBeFalse();
});

it('registers the scheduler macros and pings on success only', function () {
    Http::fake(['*' => Http::response('ok')]);
    phareBoot();
    $schedule = app(Schedule::class);

    $event = $schedule->exec('exit 0')->daily()->pingPhareOnSuccess('backup')->pingPhareOnFailure('backup');
    $event->run(app());

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://phare.test/api/v1/heartbeat/backuptoken');
});

it('pings /fail when the scheduled command fails', function () {
    Http::fake(['*' => Http::response('ok')]);
    phareBoot();

    $event = app(Schedule::class)->exec('exit 1')->daily()->pingPhareOnSuccess()->pingPhareOnFailure();
    try {
        $event->run(app());
    } catch (Throwable) {
        // the framework may rethrow the failure; only the pings matter here
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/'.PHARE_TEST_TOKEN.'/fail'));
});

it('supports pingPhare, thenPingPhare and pingPhareBefore', function () {
    Http::fake(['*' => Http::response('ok')]);
    phareBoot();

    app(Schedule::class)->exec('exit 0')->daily()->pingPhareBefore()->thenPingPhare('backup')->pingPhare()->run(app());

    Http::assertSentCount(3);
});

it('does not break the scheduler when the hub is down', function () {
    Http::fake(fn () => throw new ConnectionException('down'));
    phareBoot();

    $event = app(Schedule::class)->exec('exit 0')->daily()->pingPhareBefore()->pingPhareOnSuccess()->thenPingPhare();

    expect(fn () => $event->run(app()))->not->toThrow(Throwable::class);
});

it('exposes the facade for jobs', function () {
    Http::fake(['*' => Http::response('ok')]);
    phareBoot();

    expect(Phare::heartbeat('backup'))->toBeTrue();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/heartbeat/backuptoken'));
});

it('provides the phare:ping command', function () {
    Http::fake(['*' => Http::response('ok')]);
    phareBoot();

    $this->artisan('phare:ping', ['token' => 'backup', '--fail' => true, '--message' => 'disk full'])->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/backuptoken/fail') && str_contains($r->url(), 'msg=disk'));
});

it('makes phare:ping fail visibly when the ping is not delivered', function () {
    Http::fake(['*' => Http::response('', 404)]);
    phareBoot();

    $this->artisan('phare:ping')->assertFailed();
});

it('prints the lines to add with phare:heartbeat:install', function () {
    phareBoot();

    $this->artisan('phare:heartbeat:install', ['token' => 'mytoken'])
        ->expectsOutputToContain('PHARE_HEARTBEAT_TOKEN=mytoken')
        ->expectsOutputToContain('pingPhareOnFailure')
        ->assertSuccessful();
});

it('ships a valid composer manifest with auto-discovery', function () {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

    expect($composer['name'])->toBe('phare/laravel')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Phare\\Laravel\\')
        ->and($composer['extra']['laravel']['providers'])->toBe([PhareServiceProvider::class])
        ->and($composer['require']['php'])->toBe('^8.2');
});
