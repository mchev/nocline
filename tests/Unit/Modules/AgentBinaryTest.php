<?php

use App\Modules\Hosts\Services\AgentBinary;

uses(Tests\TestCase::class);

it('builds a versioned download url from the official repository', function () {
    config([
        'phare.official_repository' => 'mchev/phare',
        'phare.version' => '0.2.0',
        'phare.agent_download_url' => null,
    ]);

    $binary = new AgentBinary;

    expect($binary->releaseDownloadUrl('amd64'))
        ->toBe('https://github.com/mchev/phare/releases/download/v0.2.0/phare-agent-linux-amd64')
        ->and($binary->releaseDownloadPrefix())
        ->toBe('https://github.com/mchev/phare/releases/download/v0.2.0/phare-agent-linux-');
});
