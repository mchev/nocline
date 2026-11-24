<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

function kumaBackup(): string
{
    $path = sys_get_temp_dir().'/kuma-'.uniqid().'.json';
    File::put($path, json_encode(['version' => '1.23.0', 'monitorList' => [
        ['id' => 1, 'name' => 'Clients', 'type' => 'group'],
        ['id' => 6, 'name' => 'Acme', 'type' => 'group', 'parent' => 1],
        ['id' => 2, 'name' => 'Site', 'type' => 'http', 'url' => 'https://example.com', 'interval' => 20, 'timeout' => 48, 'maxretries' => 2, 'active' => 1, 'parent' => 6],
        ['id' => 3, 'name' => 'SSH', 'type' => 'port', 'hostname' => 'srv.example.com', 'port' => 22, 'interval' => 120, 'active' => 0],
        ['id' => 4, 'name' => 'Gateway', 'type' => 'ping', 'hostname' => '10.0.0.1'],
        ['id' => 5, 'name' => 'Heartbeat', 'type' => 'push'],
    ]]));

    return $path;
}

it('imports supported Kuma monitors and skips the rest', function () {
    $this->artisan('phare:import-kuma', ['file' => kumaBackup()])->assertSuccessful();

    expect(Check::count())->toBe(4);

    $site = Check::where('name', 'Site')->first();
    expect($site->type)->toBe(CheckType::Http)
        ->and($site->category)->toBe(CheckCategory::Website)
        ->and($site->interval_sec)->toBe(30)
        ->and($site->timeout_sec)->toBe(48)
        ->and($site->failure_threshold)->toBe(3)
        ->and($site->group->name)->toBe('Acme')
        ->and($site->group->parent->name)->toBe('Clients');

    $ssh = Check::where('name', 'SSH')->first();
    expect($ssh->target)->toBe('srv.example.com:22')->and($ssh->enabled)->toBeFalse();
    expect(Check::where('name', 'Gateway')->first()->execution)->toBe(CheckExecution::Probe);
});

it('imports Kuma push monitors as heartbeat checks with a fresh ping token', function () {
    $this->artisan('phare:import-kuma', ['file' => kumaBackup()])->assertSuccessful();

    $heartbeat = Check::where('name', 'Heartbeat')->first();
    expect($heartbeat->type)->toBe(CheckType::Heartbeat)
        ->and($heartbeat->category)->toBe(CheckCategory::Other)
        ->and($heartbeat->heartbeat_token)->toHaveLength(Check::HEARTBEAT_TOKEN_LENGTH)
        ->and($heartbeat->interval_sec)->toBe(60);
});

it('is idempotent and supports dry runs', function () {
    $file = kumaBackup();

    $this->artisan('phare:import-kuma', ['file' => $file, '--dry-run' => true]);
    expect(Check::count())->toBe(0);

    $this->artisan('phare:import-kuma', ['file' => $file]);
    $this->artisan('phare:import-kuma', ['file' => $file]);
    expect(Check::count())->toBe(4);
});

it('imports from the UI for tech users only, with preview', function () {
    $upload = fn () => UploadedFile::fake()->createWithContent('backup.json', File::get(kumaBackup()));

    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->post(route('checks.import-kuma'), ['file' => $upload()])->assertForbidden();

    $tech = User::factory()->tech()->create();
    $this->actingAs($tech)->post(route('checks.import-kuma'), ['file' => $upload(), 'dry_run' => true])
        ->assertSessionHas('kuma_import');
    expect(Check::count())->toBe(0);

    $this->actingAs($tech)->post(route('checks.import-kuma'), ['file' => $upload()]);
    expect(Check::count())->toBe(4);
});

it('rejects files that are not Kuma exports', function () {
    $this->actingAs(User::factory()->tech()->create())
        ->post(route('checks.import-kuma'), ['file' => UploadedFile::fake()->createWithContent('x.json', '{"a":1}')])
        ->assertSessionHasErrors('file');
});

it('reads monitors and tags straight from kuma.db', function () {
    $path = sys_get_temp_dir().'/kuma-'.uniqid().'.db';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE monitor (id INTEGER, name TEXT, type TEXT, url TEXT, hostname TEXT, port INTEGER, interval INTEGER, timeout REAL, maxretries INTEGER, active INTEGER, keyword TEXT, parent INTEGER)');
    $pdo->exec('CREATE TABLE tag (id INTEGER, name TEXT)');
    $pdo->exec('CREATE TABLE monitor_tag (monitor_id INTEGER, tag_id INTEGER)');
    $pdo->exec("INSERT INTO monitor VALUES (1,'Blog','keyword','https://blog.test',NULL,NULL,60,10.5,0,1,'Hello',NULL)");
    $pdo->exec("INSERT INTO tag VALUES (1,'acme')");
    $pdo->exec('INSERT INTO monitor_tag VALUES (1,1)');
    unset($pdo);

    $this->artisan('phare:import-kuma', ['file' => $path])->assertSuccessful();

    $check = Check::where('name', 'Blog')->first();
    expect($check->config)->toBe(['keyword' => 'Hello'])->and($check->tags)->toBe(['acme']);
});
