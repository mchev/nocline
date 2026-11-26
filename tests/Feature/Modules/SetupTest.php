<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Setup\Services\EnvWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/nocline-setup-'.uniqid();
    File::ensureDirectoryExists($this->tmp);
    File::put($this->tmp.'/.env', "APP_NAME=Old\nAPP_KEY=base64:abc\n");

    config([
        'nocline.force_installed' => false,
        'nocline.env_path' => $this->tmp.'/.env',
        'nocline.installed_marker' => $this->tmp.'/installed',
    ]);
});

afterEach(function () {
    // Restore the default connection so RefreshDatabase rolls back the right one.
    config(['database.default' => 'sqlite']);
    DB::purge('nocline_setup');
    DB::setDefaultConnection('sqlite');
    File::deleteDirectory($this->tmp);
});

function setupPayload(array $override = []): array
{
    return array_replace_recursive([
        'db' => ['driver' => 'sqlite', 'database' => test()->tmp.'/db.sqlite'],
        'admin' => [
            'name' => 'Root', 'email' => 'root@example.com',
            'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd',
        ],
        'instance' => ['name' => 'Mon NOC', 'url' => 'https://noc.example.com', 'locale' => 'fr', 'timezone' => 'Europe/Paris'],
    ], $override);
}

it('redirects everything to the wizard until installed', function () {
    $this->get('/')->assertRedirect(route('setup.show'));
    $this->get(route('dashboard'))->assertRedirect(route('setup.show'));
    $this->get(route('setup.show'))->assertOk();
});

it('reports database connectivity on the wizard', function () {
    $this->get(route('setup.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/Index')
            ->where('database.connected', true)
            ->where('database.migrated', true)
            ->where('database.use_configured', false));
});

it('locks the wizard once installed and sends visitors to the login', function () {
    config(['nocline.force_installed' => true]);

    $this->get(route('setup.show'))->assertRedirect(route('login'));
    $this->post(route('setup.store'), setupPayload())->assertRedirect(route('login'));
    expect(File::exists($this->tmp.'/installed'))->toBeFalse();
});

it('installs: migrates, creates the admin, writes .env and locks the wizard', function () {
    $this->post(route('setup.store'), setupPayload())->assertRedirect(route('login'));

    $env = File::get($this->tmp.'/.env');
    expect($env)->toContain('APP_NAME="Mon NOC"')
        ->toContain('APP_LOCALE=fr')
        ->toContain('APP_TIMEZONE=Europe/Paris')
        ->toContain('DB_CONNECTION=sqlite')
        ->toContain('APP_KEY=base64:abc')
        ->and(File::exists($this->tmp.'/installed'))->toBeTrue();

    $admin = User::on('nocline_setup')->where('email', 'root@example.com')->first();
    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('rejects weak admin passwords and bad connections without installing', function () {
    $this->post(route('setup.store'), setupPayload(['admin' => ['password' => 'weak', 'password_confirmation' => 'weak']]))
        ->assertSessionHasErrors('admin.password');

    $this->post(route('setup.store'), setupPayload(['db' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x',
    ]]))->assertSessionHasErrors('db.driver');

    expect(File::exists($this->tmp.'/installed'))->toBeFalse();
});

it('updates and appends env keys safely', function () {
    File::put($this->tmp.'/.env', "A=1\nB=2\n");

    app(EnvWriter::class)->write(['A' => 'has space', 'C' => 'p$ss"w'], $this->tmp.'/.env');

    expect(File::get($this->tmp.'/.env'))->toBe("A=\"has space\"\nB=2\nC=\"p\\\$ss\\\"w\"\n");
});

it('stores the language chosen on the first wizard step in the session', function () {
    $this->post(route('setup.locale'), ['locale' => 'fr'])->assertRedirect();
    $this->post(route('setup.locale'), ['locale' => 'xx'])->assertSessionHasErrors('locale');

    expect(session('locale'))->toBe('fr');
});

it('tests a database connection from the wizard without installing', function () {
    $this->postJson(route('setup.test-connection'), ['driver' => 'sqlite', 'database' => $this->tmp.'/probe.sqlite'])
        ->assertOk()->assertJson(['ok' => true]);

    $this->postJson(route('setup.test-connection'), ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x'])
        ->assertOk()->assertJson(['ok' => false]);

    expect(File::exists($this->tmp.'/installed'))->toBeFalse();
});

it('answers an Inertia install request with a full-page redirect to the login', function () {
    $this->post(route('setup.store'), setupPayload(), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));

    expect(File::exists($this->tmp.'/installed'))->toBeTrue();
});
