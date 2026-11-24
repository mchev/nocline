<?php

use App\Models\User;
use App\Modules\Checks\Jobs\ExecuteHubCheck;
use App\Modules\Checks\Models\Check;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('queues an immediate hub check after a website is added', function () {
    Queue::fake();

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('monitor.website'), ['url' => 'https://example.com'])
        ->assertRedirect();

    $check = Check::first();
    Queue::assertPushed(ExecuteHubCheck::class, fn (ExecuteHubCheck $job) => $job->checkId === $check->id);
});

it('runs the first http check right away', function () {
    Http::fake(['https://quick.test' => Http::response('ok', 200)]);

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('monitor.website'), ['url' => 'https://quick.test'])
        ->assertRedirect();

    $check = Check::first()->fresh(['state']);

    expect($check->state?->last_run_at)->not->toBeNull()
        ->and($check->state?->status->value)->toBe('up')
        ->and($check->state?->last_latency_ms)->not->toBeNull();
});
