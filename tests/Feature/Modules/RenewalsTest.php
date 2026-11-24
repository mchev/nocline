<?php

use App\Models\User;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Notifications\MessageMail;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Renewals\Models\RenewalReminder;
use App\Modules\Renewals\Services\CertificateInspector;
use App\Modules\Renewals\Services\DomainExpiryInspector;
use App\Modules\Renewals\Services\RegistrableDomain;
use App\Modules\Renewals\Services\RenewalSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Mail::fake();
    NotificationChannel::create(['name' => 'Ops', 'type' => 'email', 'config' => ['to' => 'ops@example.com']]);
});

/**
 * @param  array<string, string>  $expiries  registrable domain => RDAP expiration date
 */
function fakeRdap(array $expiries): void
{
    resetHttpFake();
    Http::fake(collect($expiries)->mapWithKeys(fn (string $date, string $domain) => [
        "rdap.org/domain/{$domain}" => Http::response(['events' => [
            ['eventAction' => 'registration', 'eventDate' => '2010-01-01T00:00:00Z'],
            ['eventAction' => 'expiration', 'eventDate' => $date],
        ]]),
    ])->all() + ['*' => Http::response('', 404)]);
}

function resetHttpFake(): void
{
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstance(HttpFactory::class);
}

function fakeCertificate(string $date): void
{
    app()->instance(CertificateInspector::class, new class($date) extends CertificateInspector
    {
        public function __construct(private string $date) {}

        public function expiresAt(string $host, int $port = 443, int $timeout = 10): ?CarbonImmutable
        {
            return CarbonImmutable::parse($this->date);
        }
    });
}

function renewalIn(int $days, array $override = []): Renewal
{
    return Renewal::create(array_merge(['kind' => 'domain', 'label' => 'example.com', 'expires_on' => now()->addDays($days)->toDateString()], $override));
}

it('shows renewals read-only to everyone', function () {
    renewalIn(10, ['source' => 'manual']);

    $this->actingAs(User::factory()->create())->get(route('renewals.index'))
        ->assertInertia(fn ($p) => $p->component('renewals/Index')->has('renewals', 1)
            ->where('renewals.0.source', 'manual')->where('renewals.0.days_remaining', 10)->where('can_refresh', false));
    $this->actingAs(User::factory()->tech()->create())->get(route('renewals.index'))
        ->assertInertia(fn ($p) => $p->where('can_refresh', true));
});

it('no longer exposes manual create, update or delete routes', function () {
    expect(Route::has('renewals.store'))->toBeFalse()
        ->and(Route::has('renewals.update'))->toBeFalse()
        ->and(Route::has('renewals.destroy'))->toBeFalse();
});

it('restricts the refresh route to techs and admins', function () {
    Http::fake(['rdap.org/*' => Http::response([], 404)]);

    $this->actingAs(User::factory()->create())->post(route('renewals.refresh'))->assertForbidden();
    $this->actingAs(User::factory()->tech()->create())->post(route('renewals.refresh'))->assertRedirect();
});

it('refreshes every http check from the refresh route', function () {
    fakeRdap(['example.org' => '2027-05-01T00:00:00Z']);
    fakeCertificate('2027-03-01');
    Check::factory()->create(['target' => 'https://www.shop.example.org/path']);

    $this->actingAs(User::factory()->tech()->create())->post(route('renewals.refresh'))->assertRedirect();

    expect(Renewal::where('kind', 'domain')->sole()->expires_on->toDateString())->toBe('2027-05-01')
        ->and(Renewal::where('kind', 'ssl')->count())->toBe(1);
});

it('derives the registrable domain', function (?string $host, ?string $expected) {
    expect(RegistrableDomain::from($host))->toBe($expected);
})->with([
    ['example.com', 'example.com'],
    ['WWW.Shop.Example.com.', 'example.com'],
    ['a.b.example.co.uk', 'example.co.uk'],
    ['example.org.uk', 'example.org.uk'],
    ['blog.example.com.au', 'example.com.au'],
    ['co.uk', null],
    ['localhost', null],
    ['intranet', null],
    ['192.168.1.10', null],
    ['::1', null],
    ['site.test', null],
    ['printer.local', null],
    ['', null],
    [null, null],
    ['bad host.com', null],
]);

it('reads the expiration event from RDAP', function () {
    fakeRdap(['example.com' => '2027-08-13T04:00:00Z']);

    expect((new DomainExpiryInspector)->expiresAt('www.example.com')?->toDateString())->toBe('2027-08-13');
    Http::assertSent(fn ($r) => $r->url() === 'https://rdap.org/domain/example.com');
});

it('returns null when RDAP fails, lacks an expiration or the host is unsupported', function () {
    $inspector = new DomainExpiryInspector;

    Http::fake(['rdap.org/*' => Http::response(['events' => [['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z']]])]);
    expect($inspector->expiresAt('example.com'))->toBeNull();

    resetHttpFake();
    Http::fake(['rdap.org/*' => Http::response('nope', 404)]);
    expect($inspector->expiresAt('example.com'))->toBeNull();

    resetHttpFake();
    Http::fake(['rdap.org/*' => fn () => throw new ConnectionException('boom')]);
    expect($inspector->expiresAt('example.com'))->toBeNull();

    resetHttpFake();
    Http::fake();
    expect($inspector->expiresAt('127.0.0.1'))->toBeNull()
        ->and($inspector->expiresAt('site.test'))->toBeNull();
    Http::assertNothingSent();
});

it('upserts one domain renewal per registrable domain and updates changed dates', function () {
    $group = Group::factory()->create();
    fakeRdap(['example.com' => '2027-01-10T00:00:00Z', 'other.org' => '2027-02-10T00:00:00Z']);
    Check::factory()->create(['target' => 'https://www.example.com', 'group_id' => $group->id]);
    Check::factory()->create(['target' => 'https://shop.example.com']);
    Check::factory()->create(['target' => 'http://other.org']);
    Check::factory()->create(['target' => 'http://disabled.net', 'enabled' => false]);
    Check::factory()->create(['target' => 'http://intranet.local']);

    $this->artisan('phare:renewals:scan-domains')->assertSuccessful();

    $domains = Renewal::where('kind', 'domain')->orderBy('label')->get();
    expect($domains->pluck('label')->all())->toBe(['example.com', 'other.org'])
        ->and($domains[0]->group_id)->toBe($group->id)
        ->and($domains[0]->source)->toBe('auto')
        ->and($domains[0]->expires_on->toDateString())->toBe('2027-01-10');

    fakeRdap(['example.com' => '2028-01-10T00:00:00Z', 'other.org' => '2027-02-10T00:00:00Z']);
    $this->artisan('phare:renewals:scan-domains')->assertSuccessful();

    expect(Renewal::where('kind', 'domain')->count())->toBe(2)
        ->and(Renewal::where('label', 'example.com')->sole()->expires_on->toDateString())->toBe('2028-01-10');
});

it('skips domains whose expiry is unknown and keeps the previous date', function () {
    Check::factory()->create(['target' => 'http://example.com']);
    fakeRdap(['example.com' => '2027-01-10T00:00:00Z']);
    $this->artisan('phare:renewals:scan-domains');

    resetHttpFake();
    Http::fake(['rdap.org/*' => Http::response('', 500)]);
    $this->artisan('phare:renewals:scan-domains')->assertSuccessful();

    $renewal = Renewal::sole();
    expect($renewal->expires_on->toDateString())->toBe('2027-01-10')->and($renewal->check_error)->not->toBeNull();

    Renewal::query()->delete();
    $this->artisan('phare:renewals:scan-domains')->assertSuccessful();
    expect(Renewal::count())->toBe(0);
});

it('leaves manual domain rows untouched', function () {
    renewalIn(100, ['label' => 'example.com', 'source' => 'manual']);
    fakeRdap(['example.com' => '2030-01-01T00:00:00Z']);
    Check::factory()->create(['target' => 'http://example.com']);

    $this->artisan('phare:renewals:scan-domains')->assertSuccessful();

    expect(Renewal::sole()->expires_on->toDateString())->toBe(now()->addDays(100)->toDateString());
});

it('syncs domain and ssl when a check is created via RenewalSync::forCheck', function () {
    fakeRdap(['example.com' => '2027-06-01T00:00:00Z']);
    fakeCertificate('2027-03-01');
    $check = Check::factory()->create(['target' => 'https://example.com']);

    RenewalSync::forCheck($check);

    expect(Renewal::where('kind', 'domain')->sole()->label)->toBe('example.com')
        ->and(Renewal::where('kind', 'ssl')->sole()->check_id)->toBe($check->id);
});

it('fails silently in RenewalSync::forCheck', function () {
    Http::fake(['rdap.org/*' => fn () => throw new ConnectionException('boom')]);
    $this->app->instance(CertificateInspector::class, new class extends CertificateInspector
    {
        public function expiresAt(string $host, int $port = 443, int $timeout = 10): ?CarbonImmutable
        {
            throw new RuntimeException('tls');
        }
    });
    $check = Check::factory()->create(['target' => 'https://example.com']);

    RenewalSync::forCheck($check);

    expect(Renewal::count())->toBe(0);
});

it('does nothing when expiry is far away', function () {
    renewalIn(120);

    $this->artisan('phare:renewals:remind')->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends one reminder per crossed threshold and never twice', function () {
    $renewal = renewalIn(29);

    $this->artisan('phare:renewals:remind');
    $this->artisan('phare:renewals:remind');
    Mail::assertSent(MessageMail::class, 1);

    $this->travel(16)->days(); // 13 days left -> crosses 14
    $this->artisan('phare:renewals:remind');
    Mail::assertSent(MessageMail::class, 2);

    $this->travel(7)->days(); // 6 days left -> crosses 7
    $this->artisan('phare:renewals:remind');
    Mail::assertSent(MessageMail::class, 3);

    expect(RenewalReminder::where('renewal_id', $renewal->id)->count())->toBe(3);
});

it('sends a single catch-up reminder when several thresholds were missed', function () {
    renewalIn(3);

    $this->artisan('phare:renewals:remind');

    Mail::assertSent(MessageMail::class, 1);
    expect(RenewalReminder::count())->toBe(3); // 30, 14, 7 claimed together
});

it('reminds again after the expiry date is pushed back and approaches again', function () {
    $renewal = renewalIn(5);
    $this->artisan('phare:renewals:remind');

    $renewal->update(['expires_on' => now()->addDays(20)->toDateString()]);
    $this->artisan('phare:renewals:remind');

    Mail::assertSent(MessageMail::class, 2);
});

it('flags already expired renewals', function () {
    renewalIn(-2);

    $this->artisan('phare:renewals:remind');

    Mail::assertSent(MessageMail::class, fn (MessageMail $m) => str_contains($m->subjectLine, 'EXPIRED'));
});

it('records ssl expiry of https checks without duplicating rows', function () {
    fakeCertificate('2027-03-01');
    $https = Check::factory()->create(['target' => 'https://example.com']);
    Check::factory()->create(['target' => 'http://plain.test']);

    $this->artisan('phare:renewals:scan-ssl')->assertSuccessful();
    $this->artisan('phare:renewals:scan-ssl')->assertSuccessful();

    $renewal = Renewal::sole();
    expect($renewal->check_id)->toBe($https->id)
        ->and($renewal->kind)->toBe('ssl')
        ->and($renewal->expires_on->toDateString())->toBe('2027-03-01');
});
