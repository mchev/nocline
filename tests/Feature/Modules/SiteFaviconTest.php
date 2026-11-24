<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Jobs\FetchCheckFavicon;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\SiteDetector;
use App\Modules\Checks\Services\SiteFaviconSync;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
});

it('serves a stored favicon to authenticated users', function () {
    $user = User::factory()->tech()->create();
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'favicon_path' => 'check-favicons/1.png',
    ]);
    Storage::disk('local')->put('check-favicons/1.png', fakePngBytes());

    $this->actingAs($user)
        ->get(route('checks.favicon', $check))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('stores a favicon from link tags and flags changes', function () {
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://example.com',
    ]);

    $png = fakePngBytes();

    $this->mock(SiteDetector::class, function ($mock) use ($png): void {
        $mock->shouldReceive('fetchPage')
            ->with('https://example.com')
            ->once()
            ->andReturn([
                'url' => 'https://example.com/',
                'status' => 200,
                'headers' => [],
                'body' => '<html><head><link rel="icon" href="/icon.png" sizes="32x32"></head></html>',
            ]);
        $mock->shouldReceive('fetchPage')
            ->with('https://example.com/icon.png', 0)
            ->once()
            ->andReturn([
                'url' => 'https://example.com/icon.png',
                'status' => 200,
                'headers' => [],
                'body' => $png,
            ]);
    });

    expect(app(SiteFaviconSync::class)->sync($check->fresh()))->toBeTrue();
    $check->refresh();

    expect($check->favicon_path)->toBe("check-favicons/{$check->id}.png")
        ->and($check->favicon_hash)->toBe(hash('sha256', $png))
        ->and(Storage::disk('local')->exists($check->favicon_path))->toBeTrue();

    $other = fakePngBytes().'x';

    $this->mock(SiteDetector::class, function ($mock) use ($other): void {
        $mock->shouldReceive('fetchPage')->andReturn(
            [
                'url' => 'https://example.com/',
                'status' => 200,
                'headers' => [],
                'body' => '<html><link rel="icon" href="/icon.png"></html>',
            ],
            [
                'url' => 'https://example.com/icon.png',
                'status' => 200,
                'headers' => [],
                'body' => $other,
            ],
        );
    });

    app(SiteFaviconSync::class)->sync($check->fresh());
    $check->refresh();

    expect($check->tags)->toContain('favicon-changed');
});

it('queues favicon fetch when a website monitor is created', function () {
    Queue::fake();
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('monitor.website'), ['url' => 'https://example.com'])->assertRedirect();

    $check = Check::first();
    Queue::assertPushed(FetchCheckFavicon::class, fn ($job) => $job->checkId === $check->id);
});

function fakePngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}
