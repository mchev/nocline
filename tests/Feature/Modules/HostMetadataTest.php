<?php

use App\Modules\Hosts\Models\Host;

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
