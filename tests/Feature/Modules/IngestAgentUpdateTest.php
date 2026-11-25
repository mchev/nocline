<?php

use App\Modules\Hosts\Models\Host;

it('includes agent_update in ingest when hub is newer and checksums are available', function () {
    $dir = storage_path('app/agent-test-'.uniqid());
    mkdir($dir, 0o755, true);
    file_put_contents("$dir/phare-agent-linux-amd64", str_repeat('x', 600_000));
    file_put_contents("$dir/phare-agent-linux-arm64", str_repeat('y', 600_000));

    config([
        'phare.version' => '0.2.0',
        'phare.agent_auto_update' => true,
        'phare.agent_binary_dir' => $dir,
        'phare.agent_download_url' => null,
    ]);

    Host::factory()->enrolled()->create();

    $response = $this->withToken('pha_test')->postJson(route('api.ingest'), [
        'agent_version' => '0.1.0',
        'metrics' => [['key' => 'cpu.usage_pct', 'min' => 1, 'max' => 2, 'avg' => 1.5, 'p95' => 2]],
    ])->assertAccepted();

    expect($response->json('accepted'))->toBe(1)
        ->and($response->json('agent_update.version'))->toBe('0.2.0')
        ->and($response->json('agent_update.binaries.amd64.sha256'))->toHaveLength(64);
});

it('omits agent_update when the agent is current', function () {
    config(['phare.version' => '0.1.0', 'phare.agent_auto_update' => true]);
    Host::factory()->enrolled()->create();

    $response = $this->withToken('pha_test')->postJson(route('api.ingest'), [
        'agent_version' => '0.1.0',
        'metrics' => [['key' => 'cpu.usage_pct', 'min' => 1, 'max' => 2, 'avg' => 1.5, 'p95' => 2]],
    ])->assertAccepted();

    expect($response->json('agent_update'))->toBeNull();
});

it('omits agent_update when disabled on the hub', function () {
    config(['phare.version' => '0.2.0', 'phare.agent_auto_update' => false]);
    Host::factory()->enrolled()->create();

    $response = $this->withToken('pha_test')->postJson(route('api.ingest'), [
        'agent_version' => '0.1.0',
        'metrics' => [['key' => 'cpu.usage_pct', 'min' => 1, 'max' => 2, 'avg' => 1.5, 'p95' => 2]],
    ])->assertAccepted();

    expect($response->json('agent_update'))->toBeNull();
});
