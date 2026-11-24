<?php

use App\Models\User;
use App\Modules\Coffre\Models\VaultAccessLog;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Coffre\Services\VaultCrypto;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['vault.master_key' => base64_encode(random_bytes(32))]);
});

function createEntry($test, array $override = [])
{
    return $test->post(route('coffre.store'), array_merge([
        'label' => 'OVH client A', 'username' => 'root-user', 'secret' => 'S3cr3t-Value!', 'notes' => 'private note',
        'password' => 'password',
    ], $override));
}

it('encrypts every sensitive column at rest', function () {
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this)->assertRedirect();

    $raw = json_encode(DB::table('vault_entries')->first());
    expect($raw)->not->toContain('S3cr3t-Value!')
        ->not->toContain('root-user')
        ->not->toContain('private note');
});

it('requires the current password to create and to reveal', function () {
    $this->actingAs(User::factory()->tech()->create());

    createEntry($this, ['password' => 'wrong'])->assertSessionHasErrors('password');
    expect(VaultEntry::count())->toBe(0);

    createEntry($this);
    $entry = VaultEntry::firstOrFail();

    $this->postJson(route('coffre.reveal', $entry), ['password' => 'wrong'])->assertUnprocessable();
    $this->postJson(route('coffre.reveal', $entry), [])->assertUnprocessable();
});

it('reveals the plaintext after re-auth and writes an audit row without secrets', function () {
    $user = User::factory()->tech()->create();
    $this->actingAs($user);
    createEntry($this);
    $entry = VaultEntry::firstOrFail();

    $this->postJson(route('coffre.reveal', $entry), ['password' => 'password'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson(['username' => 'root-user', 'secret' => 'S3cr3t-Value!', 'notes' => 'private note']);

    $this->postJson(route('coffre.copied', $entry))->assertOk();

    expect(VaultAccessLog::pluck('action')->all())->toBe(['create', 'view', 'copy'])
        ->and(json_encode(VaultAccessLog::all()))->not->toContain('S3cr3t')
        ->and(VaultAccessLog::where('action', 'view')->first()->user_id)->toBe($user->id);
});

it('never ships secrets in the index props', function () {
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this);

    $response = $this->get(route('coffre.index'))->assertOk();

    expect($response->getContent())->not->toContain('S3cr3t-Value!')->not->toContain('secret_enc');
});

it('forbids viewers entirely and limits deletion and audit to admins', function () {
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this);
    $entry = VaultEntry::firstOrFail();

    $this->actingAs(User::factory()->create());
    $this->get(route('coffre.index'))->assertForbidden();
    $this->postJson(route('coffre.reveal', $entry), ['password' => 'password'])->assertForbidden();

    $this->actingAs(User::factory()->tech()->create());
    $this->delete(route('coffre.destroy', $entry), ['password' => 'password'])->assertForbidden();
    $this->get(route('coffre.audit'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('coffre.audit'))->assertOk();
    $this->delete(route('coffre.destroy', $entry), ['password' => 'password'])->assertRedirect();

    expect(VaultEntry::count())->toBe(0)
        ->and(VaultAccessLog::where('action', 'delete')->count())->toBe(1)
        ->and(VaultAccessLog::count())->toBe(2); // audit trail survives deletion
});

it('keeps the audit log append-only', function () {
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this);
    $log = VaultAccessLog::firstOrFail();

    expect(fn () => $log->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('keeps the secret when editing without a new one and rotates the DEK payload when changed', function () {
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this);
    $entry = VaultEntry::firstOrFail();
    $before = $entry->secret_enc;

    $this->patch(route('coffre.update', $entry), ['label' => 'Renamed', 'password' => 'password'])->assertRedirect();
    expect($entry->refresh()->secret_enc)->toBe($before)->and($entry->label)->toBe('Renamed');

    $this->patch(route('coffre.update', $entry), ['label' => 'Renamed', 'secret' => 'New-Secret!', 'password' => 'password']);
    expect($entry->refresh()->reveal(app(VaultCrypto::class))['secret'])->toBe('New-Secret!');
});

it('fails closed without a valid master key or with tampered data', function () {
    $crypto = app(VaultCrypto::class);
    $key = $crypto->newDek();
    $payload = $crypto->encrypt('hello', $key);

    expect($crypto->decrypt($payload, $key))->toBe('hello')
        ->and(fn () => $crypto->decrypt($payload, $crypto->newDek()))->toThrow(RuntimeException::class)
        ->and(fn () => $crypto->decrypt(base64_encode(substr(base64_decode($payload), 0, -1).'x'), $key))->toThrow(RuntimeException::class);

    config(['vault.master_key' => null]);
    $this->actingAs(User::factory()->tech()->create());
    createEntry($this)->assertStatus(503);

    config(['vault.master_key' => config('app.key') ? substr((string) config('app.key'), 7) : 'x']);
    expect(app(VaultCrypto::class)->isConfigured())->toBeFalse();
});
