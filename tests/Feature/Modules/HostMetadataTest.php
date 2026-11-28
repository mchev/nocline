<?php

use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Notifications\MessageMail;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetaPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EndOfLifeCatalogFixture;

it('stores host metadata from agent ingest', function () {
    $host = Host::factory()->enrolled('pha_meta_test')->create();
    $token = 'pha_meta_test';

    $response = $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.1.2',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 12.5],
        ],
        'host_meta' => [
            'hostname' => 'web-01',
            'public_ipv4' => '203.0.113.10',
            'distro' => 'Ubuntu 22.04.3 LTS',
            'provider' => 'hetzner',
            'provider_region' => 'fsn1',
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertAccepted();
    $host->refresh();

    expect($host->agent_meta['hostname'])->toBe('web-01')
        ->and($host->agent_meta['public_ipv4'])->toBe('203.0.113.10')
        ->and($host->agent_meta['provider'])->toBe('hetzner')
        ->and($host->os)->toBe('Ubuntu 22.04.3 LTS');
});

it('stores stack versions from agent ingest', function () {
    $host = Host::factory()->enrolled('pha_stack_test')->create();
    $token = 'pha_stack_test';

    $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.2.0',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 5],
        ],
        'host_meta' => [
            'stack' => [
                'php' => '8.3.12',
                'node' => '22.11.0',
                'postgresql' => '16.2',
            ],
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertAccepted();

    $host->refresh();

    expect($host->agent_meta['stack'])->toMatchArray([
        'php' => '8.3.12',
        'node' => '22.11.0',
        'postgresql' => '16.2',
    ]);
});

it('exposes stack warnings from the presenter', function () {
    Carbon::setTestNow('2026-11-26');
    config(['nocline.stack_eol_check' => true]);
    EndOfLifeCatalogFixture::seedMinimalCatalog();

    $host = Host::factory()->enrolled()->create([
        'agent_meta' => [
            'stack' => [
                'php' => '8.1.0',
                'node' => '22.14.0',
            ],
        ],
    ]);

    $presentation = HostMetaPresenter::forHost($host);

    expect($presentation['stack_warnings'])->toHaveKey('php')
        ->and($presentation['stack_warnings'])->not->toHaveKey('node');

    Carbon::setTestNow();
});

it('stores reboot required flags from agent ingest', function () {
    $host = Host::factory()->enrolled('pha_reboot_test')->create();
    $token = 'pha_reboot_test';

    $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.2.0',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 1],
        ],
        'host_meta' => [
            'reboot_required' => true,
            'reboot_required_since' => now()->subHour()->toIso8601String(),
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertAccepted();

    $host->refresh();

    expect($host->agent_meta['reboot_required'])->toBeTrue()
        ->and($host->agent_meta['reboot_required_since'])->not->toBeEmpty();

    $presentation = HostMetaPresenter::forHost($host);

    expect($presentation['reboot_required'])->toBeTrue()
        ->and($presentation['reboot_required_message'])->toContain('reboot');
});

it('clears reboot since when the agent reports no reboot needed', function () {
    $token = 'pha_reboot_clear';
    $host = Host::factory()->enrolled($token)->create([
        'agent_meta' => [
            'reboot_required' => true,
            'reboot_required_since' => now()->subDay()->toIso8601String(),
        ],
    ]);

    $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.2.0',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 1],
        ],
        'host_meta' => [
            'reboot_required' => false,
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertAccepted();

    $host->refresh();

    expect($host->agent_meta['reboot_required'])->toBeFalse()
        ->and($host->agent_meta)->not->toHaveKey('reboot_required_since');
});

it('notifies channels once when reboot becomes required', function () {
    Mail::fake();
    Cache::flush();

    NotificationChannel::create([
        'name' => 'Ops',
        'type' => 'email',
        'enabled' => true,
        'config' => ['to' => 'ops@example.test'],
    ]);

    $host = Host::factory()->enrolled('pha_reboot_notify')->create();

    $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.2.0',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 1],
        ],
        'host_meta' => [
            'reboot_required' => true,
            'reboot_required_since' => now()->toIso8601String(),
        ],
    ], [
        'Authorization' => 'Bearer '.'pha_reboot_notify',
    ])->assertAccepted();

    Mail::assertSent(MessageMail::class, 1);

    $this->postJson('/api/v1/ingest', [
        'agent_version' => '0.2.0',
        'window_sec' => 60,
        'metrics' => [
            ['key' => 'cpu.usage_pct', 'avg' => 2],
        ],
        'host_meta' => [
            'reboot_required' => true,
        ],
    ], [
        'Authorization' => 'Bearer '.'pha_reboot_notify',
    ])->assertAccepted();

    Mail::assertSent(MessageMail::class, 1);
});

it('stores host metadata on enroll', function () {
    $host = Host::factory()->create();
    $enroll = $host->issueEnrollmentToken();

    $this->postJson('/api/v1/enroll', [
        'token' => $enroll,
        'agent_version' => '0.1.2',
        'os' => 'linux',
        'host_meta' => [
            'public_ipv4' => '198.51.100.4',
            'provider' => 'digitalocean',
        ],
    ])->assertCreated();

    $host->refresh();
    expect($host->enrolled_at)->not->toBeNull()
        ->and($host->agent_meta['provider'])->toBe('digitalocean');
});
