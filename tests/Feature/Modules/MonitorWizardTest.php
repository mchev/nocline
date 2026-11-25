<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;

beforeEach(fn () => $this->actingAs(User::factory()->tech()->create()));

it('renders the "what do you want to monitor" page', function () {
    $this->get(route('monitor.create'))
        ->assertInertia(fn ($page) => $page->component('monitor/Create')
            ->has('groups')
            ->has('has_probe')
            ->has('object_storage_providers'));
});

it('adds an OVH object storage platform check', function () {
    $this->post(route('monitor.object-storage'), [
        'provider' => 'ovh',
        'region' => 'gra',
        'level' => 'platform',
    ])->assertRedirect(route('dashboard'));

    $check = Check::first();
    expect($check->type)->toBe(CheckType::Tcp)
        ->and($check->target)->toBe('s3.gra.io.cloud.ovh.net:443')
        ->and($check->tags)->toContain('object-storage', 'ovh');
});

it('adds an object storage bucket HTTP check', function () {
    $this->post(route('monitor.object-storage'), [
        'provider' => 'ovh',
        'region' => 'sbg',
        'level' => 'bucket',
        'bucket' => 'my-bucket',
        'health_path' => 'health.txt',
    ])->assertRedirect(route('dashboard'));

    expect(Check::first()->type)->toBe(CheckType::Http)
        ->and(Check::first()->target)->toBe('https://my-bucket.s3.sbg.io.cloud.ovh.net/health.txt');
});

it('routes object storage backup level to heartbeat setup', function () {
    $response = $this->post(route('monitor.object-storage'), [
        'provider' => 'ovh',
        'region' => 'gra',
        'level' => 'backup',
        'name' => 'Nightly S3 backup',
    ]);

    $check = Check::first();
    $response->assertRedirect(route('monitor.heartbeat', $check));
    expect($check->type)->toBe(CheckType::Heartbeat);
});

it('adds a website from a bare domain and creates the group on the fly', function () {
    $this->post(route('monitor.website'), ['url' => 'www.example.com', 'group_name' => 'Acme'])
        ->assertRedirect(route('dashboard'));

    $check = Check::first();
    expect($check->type)->toBe(CheckType::Http)
        ->and($check->category)->toBe(CheckCategory::Website)
        ->and($check->target)->toBe('https://www.example.com')
        ->and($check->name)->toBe('example.com')
        ->and($check->group->name)->toBe('Acme');
});

it('rejects an invalid website address', function () {
    $this->post(route('monitor.website'), ['url' => 'not a url'])->assertSessionHasErrors('url');
});

it('adds a server: host + presence check, then shows a one-line install command', function () {
    $response = $this->post(route('monitor.server'), ['name' => 'Web 1']);

    $host = Host::first();
    $response->assertRedirect(route('monitor.install', $host));
    expect(Check::first()->type)->toBe(CheckType::Agent)->and(Check::first()->host_id)->toBe($host->id);

    $this->get(route('monitor.install', $host))
        ->assertInertia(fn ($page) => $page->component('monitor/Install')
            ->where('host.connected', false)
            ->where('install_command', fn ($c) => str_starts_with($c, 'curl -fsSL ') && str_ends_with($c, '| sudo sh')));
});

it('serves an agent update script without enrollment', function () {
    $script = $this->get(route('install.agent-update'))->assertOk()->getContent();
    expect($script)->toStartWith('#!/bin/sh')
        ->toContain('phare-agent-linux-')
        ->toContain('Restarting the agent')
        ->not->toContain('Connecting this server');
});

it('serves a working installer script for a valid token only', function () {
    $host = Host::factory()->create();
    $token = $host->issueEnrollmentToken();

    $script = $this->get(route('install.script', $token))->assertOk()->getContent();
    expect($script)->toStartWith('#!/bin/sh')->toContain("TOKEN='{$token}'")->toContain('releases/download/')->toContain('phare-agent-linux-');

    $this->get(route('install.script', 'phe_wrong'))->assertOk()->assertSee('expired');
    Host::enrollWithToken($token);
    $this->get(route('install.script', $token))->assertSee('expired');
});

it('redirects agent downloads to the official versioned GitHub release', function () {
    config([
        'phare.official_repository' => 'mchev/phare',
        'phare.version' => '0.1.0',
        'phare.agent_download_url' => null,
        'phare.agent_binary_dir' => sys_get_temp_dir().'/no-agent-'.uniqid(),
    ]);
    $this->get(route('install.binary', 'linux-amd64'))
        ->assertRedirect('https://github.com/mchev/phare/releases/download/v0.1.0/phare-agent-linux-amd64');
    $this->get(route('install.binary', 'linux-mips'))->assertNotFound();

    config(['phare.agent_download_url' => 'https://dl.example.com/phare-agent-linux-{arch}']);
    $this->get(route('install.binary', 'linux-arm64'))->assertRedirect('https://dl.example.com/phare-agent-linux-arm64');
});

it('can serve a locally built agent binary in development', function () {
    $dir = sys_get_temp_dir().'/phare-agent-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/phare-agent-linux-amd64', str_repeat('x', 2048));
    config(['phare.agent_binary_dir' => $dir]);
    $this->get(route('install.binary', 'linux-amd64'))->assertOk();
});

it('adds a mail server and a ping check', function () {
    $this->post(route('monitor.service'), ['kind' => 'mail', 'host' => 'mail.example.com', 'port' => 587])->assertRedirect();
    $this->post(route('monitor.service'), ['kind' => 'ping', 'host' => '10.0.0.1'])->assertRedirect();

    $mail = Check::where('target', 'mail.example.com:587')->first();
    expect($mail->type)->toBe(CheckType::Tcp)->and($mail->category)->toBe(CheckCategory::Mail);
    expect(Check::where('target', '10.0.0.1')->first()->execution)->toBe(CheckExecution::Probe);

    $this->post(route('monitor.service'), ['kind' => 'port', 'host' => 'x.example.com'])->assertSessionHasErrors('port');
});

it('does not let viewers add monitors', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->post(route('monitor.website'), ['url' => 'example.com'])->assertForbidden();
    expect(Group::count())->toBe(0);
});
