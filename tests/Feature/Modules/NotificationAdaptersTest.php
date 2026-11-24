<?php

use App\Models\User;
use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Services\AdapterRegistry;
use App\Modules\Alertes\Services\MessageNotifier;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Http;

it('discovers the built-in adapters', function () {
    expect(app(AdapterRegistry::class)->keys())
        ->toContain('email', 'webhook', 'slack', 'discord', 'brevo');
});

it('creates a channel from the fields declared by its adapter', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('alertes.channels.store'), [
        'name' => 'Ops Slack', 'type' => 'slack', 'webhook_url' => 'https://hooks.slack.com/services/T/B/x', 'ignored' => 'x',
    ])->assertRedirect();

    expect(NotificationChannel::first()->config)->toBe(['webhook_url' => 'https://hooks.slack.com/services/T/B/x']);

    $this->actingAs($admin)->post(route('alertes.channels.store'), ['name' => 'Bad', 'type' => 'brevo', 'to' => 'a@b.test'])
        ->assertSessionHasErrors(['api_key', 'sender_email']);
    $this->actingAs($admin)->post(route('alertes.channels.store'), ['name' => 'Bad', 'type' => 'nope'])
        ->assertSessionHasErrors('type');
});

it('never exposes secrets and keeps them when left blank on edit', function () {
    $admin = User::factory()->admin()->create();
    $channel = NotificationChannel::create(['name' => 'Brevo', 'type' => 'brevo', 'config' => ['api_key' => 'xkeysib-secret', 'sender_email' => 'a@b.test', 'to' => 'ops@b.test']]);

    $this->actingAs($admin)->get(route('alertes.channels.index'))
        ->assertInertia(fn ($page) => $page->where('channels.0.config', ['sender_email' => 'a@b.test', 'to' => 'ops@b.test']));

    $this->actingAs($admin)->patch(route('alertes.channels.update', $channel), [
        'name' => 'Brevo', 'type' => 'brevo', 'api_key' => '', 'sender_email' => 'a@b.test', 'to' => 'new@b.test',
    ])->assertSessionDoesntHaveErrors();

    expect($channel->refresh()->config)->toBe(['api_key' => 'xkeysib-secret', 'sender_email' => 'a@b.test', 'to' => 'new@b.test']);
});

it('sends through Slack, Discord and Brevo', function () {
    Http::fake();
    NotificationChannel::create(['name' => 's', 'type' => 'slack', 'config' => ['webhook_url' => 'https://hooks.slack.test/x']]);
    NotificationChannel::create(['name' => 'd', 'type' => 'discord', 'config' => ['webhook_url' => 'https://discord.test/x']]);
    NotificationChannel::create(['name' => 'b', 'type' => 'brevo', 'config' => ['api_key' => 'k', 'sender_email' => 'a@b.test', 'to' => 'ops@b.test']]);

    expect(app(MessageNotifier::class)->broadcast('Cert expires', 'in 7 days'))->toBe(3);

    Http::assertSent(fn ($r) => $r->url() === 'https://hooks.slack.test/x' && str_contains($r['text'], 'Cert expires'));
    Http::assertSent(fn ($r) => $r->url() === 'https://discord.test/x' && str_contains($r['content'], 'in 7 days'));
    Http::assertSent(fn ($r) => $r->url() === 'https://api.brevo.com/v3/smtp/email' && $r->hasHeader('api-key', 'k') && $r['to'][0]['email'] === 'ops@b.test');
});

it('sends a test notification and reports failures', function () {
    $admin = User::factory()->admin()->create();
    $channel = NotificationChannel::create(['name' => 's', 'type' => 'slack', 'config' => ['webhook_url' => 'https://hooks.slack.test/x']]);

    Http::fake(['*' => Http::response('', 200)]);
    $this->actingAs($admin)->post(route('alertes.channels.test', $channel))->assertRedirect();
    Http::assertSentCount(1);

    Http::fake(['*' => Http::response('', 500)]);
    $this->actingAs($admin)->post(route('alertes.channels.test', $channel))->assertRedirect();

    $this->actingAs(User::factory()->tech()->create())->post(route('alertes.channels.test', $channel))->assertForbidden();
});

it('accepts a contributed adapter registered through config', function () {
    config(['phare.notification_adapters' => [ExampleTestAdapter::class]]);
    app()->forgetInstance(AdapterRegistry::class);

    expect(app(AdapterRegistry::class)->keys())->toContain('example');

    NotificationChannel::create(['name' => 'x', 'type' => 'example', 'config' => ['token' => 't']]);
    expect(app(MessageNotifier::class)->broadcast('Hi', 'there'))->toBe(1)
        ->and(ExampleTestAdapter::$sent)->toBe(['t:Hi']);
});

class ExampleTestAdapter implements NotificationAdapter
{
    /** @var array<int, string> */
    public static array $sent = [];

    public static function key(): string
    {
        return 'example';
    }

    public static function label(): string
    {
        return 'Example';
    }

    public static function fields(): array
    {
        return [new AdapterField('token', 'Token', 'password', secret: true)];
    }

    public function send(array $config, AlertMessage $message): void
    {
        self::$sent[] = $config['token'].':'.$message->subject;
    }
}
