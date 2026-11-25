<?php

use App\Modules\Hosts\Services\AgentBinary;
use Tests\TestCase;

uses(TestCase::class);

it('builds a versioned download url from the official repository', function () {
    config([
        'nocline.official_repository' => 'mchev/nocline',
        'nocline.version' => '0.2.0',
        'nocline.agent_download_url' => null,
    ]);

    $binary = new AgentBinary;

    expect($binary->releaseDownloadUrl('amd64'))
        ->toBe('https://github.com/mchev/nocline/releases/download/v0.2.0/nocline-agent-linux-amd64')
        ->and($binary->releaseDownloadPrefix())
        ->toBe('https://github.com/mchev/nocline/releases/download/v0.2.0/nocline-agent-linux-');
});
