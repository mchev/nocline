<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\HostResolver;
use App\Modules\Checks\Services\SiteDetector;
use App\Modules\Renewals\Services\CertificateInspector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeDetectDns(array $map = []): void
{
    app()->instance(HostResolver::class, new class($map) extends HostResolver
    {
        public function __construct(private array $map) {}

        public function resolve(string $host): array
        {
            return $this->map[$host] ?? ['93.184.216.34'];
        }
    });
}

function fakeDetectCertificate(?CarbonImmutable $expires): void
{
    app()->instance(CertificateInspector::class, new class($expires) extends CertificateInspector
    {
        public function __construct(private ?CarbonImmutable $expires) {}

        public function expiresAt(string $host, int $port = 443, int $timeout = 10): ?CarbonImmutable
        {
            return $this->expires;
        }
    });
}

const DETECT_HEALTH_PAGE = '<html><body><div>Application up</div></body></html>';

beforeEach(function () {
    fakeDetectDns();
    fakeDetectCertificate(now()->addDays(40)->toImmutable());
});

it('detects a Laravel 11 site with a real health page and a session cookie', function () {
    Http::fake([
        'https://shop.example.com/up' => Http::response(DETECT_HEALTH_PAGE),
        'https://shop.example.com/nocline-*' => Http::response('Not found', 404),
        'https://shop.example.com' => Http::response('<html></html>', 200, ['Set-Cookie' => ['laravel_session=abc; path=/; HttpOnly', 'XSRF-TOKEN=xyz; path=/']]),
        '*' => Http::response('', 404),
    ]);

    $result = app(SiteDetector::class)->inspect('https://shop.example.com');

    expect($result->reachable)->toBeTrue()
        ->and($result->framework)->toBe(SiteFramework::Laravel)
        ->and($result->hasHealthEndpoint)->toBeTrue()
        ->and($result->certificateDaysLeft)->toBeIn([39, 40])
        ->and($result->toResponse()['suggestions'][0]['key'])->toBe('watch_health');

    Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0], 'Nocline-Detect/'));
});

it('does not take a catch-all SPA for a health endpoint', function () {
    Http::fake(['*' => Http::response('<html><div id="app"></div>Application up</html>')]);

    $result = app(SiteDetector::class)->inspect('https://spa.example.com');

    expect($result->reachable)->toBeTrue()
        ->and($result->hasHealthEndpoint)->toBeFalse()
        ->and($result->framework)->toBe(SiteFramework::Unknown);
});

it('does not take a catch-all SPA without the marker for Laravel', function () {
    Http::fake(['*' => Http::response('<html><div id="app"></div></html>')]);

    $result = app(SiteDetector::class)->inspect('https://spa.example.com');

    expect($result->hasHealthEndpoint)->toBeFalse()->and($result->framework)->toBe(SiteFramework::Unknown);
});

it('detects a WordPress site', function () {
    Http::fake([
        'https://blog.example.com' => Http::response('<html><link href="https://blog.example.com/wp-content/themes/x/style.css"><meta name="generator" content="WordPress 6.5"></html>'),
        '*' => Http::response('', 404),
    ]);

    $result = app(SiteDetector::class)->inspect('https://blog.example.com');

    expect($result->framework)->toBe(SiteFramework::WordPress)
        ->and($result->wordpressVersion)->toBe('6.5')
        ->and($result->hasHealthEndpoint)->toBeFalse();
});

it('detects WordPress through its API when the homepage gives nothing away', function () {
    Http::fake([
        'https://blog.example.com/wp-json/' => Http::response('{"name":"x","namespaces":["wp/v2"]}'),
        '*' => Http::response('<html></html>'),
    ]);

    expect(app(SiteDetector::class)->inspect('https://blog.example.com')->framework)->toBe(SiteFramework::WordPress);
});

it('reads WordPress version from the RSS feed when the homepage hides it', function () {
    Http::fake([
        'https://blog.example.com/feed/' => Http::response('<?xml version="1.0"?><rss><channel><generator>https://wordpress.org/?v=6.7.2</generator></channel></rss>'),
        '*' => Http::response('<html><link href="/wp-content/themes/x/style.css"></html>'),
    ]);

    expect(app(SiteDetector::class)->inspect('https://blog.example.com')->wordpressVersion)->toBe('6.7.2');
});

it('reports an unreachable site neutrally', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = app(SiteDetector::class)->inspect('https://down.example.com');

    expect($result->reachable)->toBeFalse()->and($result->toResponse()['suggestions'])->toBe([]);
});

it('follows same-site redirects and https upgrades only', function () {
    Http::fake([
        'http://example.com' => Http::response('', 301, ['Location' => 'https://www.example.com/']),
        'https://www.example.com/' => Http::response('<html></html>'),
        '*' => Http::response('', 404),
    ]);

    expect(app(SiteDetector::class)->inspect('http://example.com')->finalUrl)->toBe('https://www.example.com/');
});

it('never follows a redirect to another host, even to the cloud metadata address', function () {
    Http::fake([
        'https://example.com' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('secret'),
    ]);

    $result = app(SiteDetector::class)->inspect('https://example.com');

    expect($result->finalUrl)->toBe('https://example.com')->and($result->framework)->toBe(SiteFramework::Unknown);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254'));
});

it('stops after three redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => '/again'])]);

    app(SiteDetector::class)->inspect('https://loop.example.com');

    Http::assertSentCount(4 + 1 + 1);
});

it('refuses private targets without sending a request', function (string $url) {
    Http::fake(['*' => Http::response('ok')]);

    expect(app(SiteDetector::class)->inspect($url)->reachable)->toBeFalse();
    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1',
    'lan' => 'http://192.168.1.10',
    'metadata' => 'http://169.254.169.254/latest',
    'ipv6 loopback' => 'http://[::1]/',
    'ipv4 mapped ipv6' => 'http://[::ffff:127.0.0.1]/',
    'decimal ip' => 'http://2130706433',
    'hex ip' => 'http://0x7f000001',
    'short ip' => 'http://127.1',
    'ftp' => 'ftp://example.com',
    'file' => 'file:///etc/passwd',
    'credentials' => 'https://user:pass@example.com',
    'odd port' => 'https://example.com:22',
    'not a url' => 'nonsense',
]);

it('refuses a host name that resolves to a private address (DNS rebinding)', function () {
    fakeDetectDns(['evil.example.com' => ['93.184.216.34', '10.0.0.5']]);
    Http::fake(['*' => Http::response('ok')]);

    expect(app(SiteDetector::class)->inspect('https://evil.example.com')->reachable)->toBeFalse();
    Http::assertNothingSent();
});

it('refuses a host name that does not resolve', function () {
    fakeDetectDns(['nowhere.example.com' => []]);
    Http::fake(['*' => Http::response('ok')]);

    expect(app(SiteDetector::class)->inspect('https://nowhere.example.com')->reachable)->toBeFalse();
    Http::assertNothingSent();
});

it('can be allowed to reach private networks by configuration', function () {
    config(['nocline.detect_allow_private_networks' => true]);
    fakeDetectDns(['app.test' => ['127.0.0.1']]);
    Http::fake(['*' => Http::response('<html></html>')]);

    expect(app(SiteDetector::class)->inspect('http://app.test')->reachable)->toBeTrue();
    expect(app(SiteDetector::class)->inspect('http://192.168.1.10')->reachable)->toBeTrue();
    expect(app(SiteDetector::class)->inspect('http://2130706433')->reachable)->toBeFalse();
});

it('reads at most a limited part of a huge body', function () {
    Http::fake(['*' => Http::response(str_repeat('a', 2_000_000).'wp-content')]);

    expect(app(SiteDetector::class)->inspect('https://huge.example.com')->framework)->toBe(SiteFramework::Unknown);
});

describe('endpoint', function () {
    it('returns plain-language detection for a technician', function () {
        Http::fake(['*' => Http::response('<html></html>')]);

        $this->actingAs(User::factory()->tech()->create())
            ->postJson(route('monitor.detect'), ['url' => 'example.com'])
            ->assertOk()
            ->assertJsonPath('reachable', true)
            ->assertJsonStructure(['summary', 'suggestions', 'framework']);
    });

    it('requires authentication', function () {
        $this->postJson(route('monitor.detect'), ['url' => 'example.com'])->assertUnauthorized();
    });

    it('is forbidden to viewers', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->postJson(route('monitor.detect'), ['url' => 'example.com'])->assertForbidden();
    });

    it('validates the address', function () {
        $this->actingAs(User::factory()->tech()->create())
            ->postJson(route('monitor.detect'), ['url' => 'not a url'])->assertJsonValidationErrors('url');
    });

    it('answers like an unreachable site for private addresses', function () {
        Http::fake();

        $this->actingAs(User::factory()->tech()->create())
            ->postJson(route('monitor.detect'), ['url' => 'http://127.0.0.1'])
            ->assertOk()->assertJsonPath('reachable', false);
        Http::assertNothingSent();
    });

    it('is throttled', function () {
        $this->withRouteThrottling();
        Http::fake(['*' => Http::response('<html></html>')]);
        $this->actingAs(User::factory()->tech()->create());

        foreach (range(1, 20) as $_) {
            $this->postJson(route('monitor.detect'), ['url' => 'example.com'])->assertOk();
        }

        $this->postJson(route('monitor.detect'), ['url' => 'example.com'])->assertTooManyRequests();
    });
});

describe('adding a website', function () {
    it('creates a tagged site and its health check', function () {
        $this->actingAs(User::factory()->tech()->create())
            ->post(route('monitor.website'), ['url' => 'https://shop.example.com/some/path', 'watch_health' => true, 'framework' => 'laravel', 'group_name' => 'Acme'])
            ->assertRedirect(route('dashboard'));

        $site = Check::where('target', 'https://shop.example.com/some/path')->firstOrFail();
        $health = Check::where('name', 'shop.example.com — health')->firstOrFail();

        expect($site->tags)->toBe(['laravel'])
            ->and($health->target)->toBe('https://shop.example.com/up')
            ->and($health->tags)->toBe(['laravel'])
            ->and($health->group_id)->toBe($site->group_id)
            ->and($health->interval_sec)->toBe(60)
            ->and(Check::count())->toBe(2);
    });

    it('creates a single untagged check by default', function () {
        $this->actingAs(User::factory()->tech()->create())
            ->post(route('monitor.website'), ['url' => 'example.com'])->assertRedirect();

        expect(Check::count())->toBe(1)->and(Check::first()->tags)->toBeNull();
    });

    it('rejects an unknown framework', function () {
        $this->actingAs(User::factory()->tech()->create())
            ->post(route('monitor.website'), ['url' => 'example.com', 'framework' => 'rails'])
            ->assertSessionHasErrors('framework');
    });

    it('is forbidden to viewers', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
            ->post(route('monitor.website'), ['url' => 'example.com', 'watch_health' => true])->assertForbidden();

        expect(Check::count())->toBe(0);
    });
});
