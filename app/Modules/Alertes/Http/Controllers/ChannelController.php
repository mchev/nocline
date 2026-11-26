<?php

namespace App\Modules\Alertes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alertes\Http\Requests\ChannelRequest;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Services\AdapterRegistry;
use App\Modules\Alertes\Services\NotificationPreferences;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ChannelController extends Controller
{
    public function index(AdapterRegistry $registry, NotificationPreferences $preferences): Response
    {
        $this->authorize('viewAny', NotificationChannel::class);

        return Inertia::render('alertes/Index', [
            'notification_preferences' => $preferences->all(),
            'channels' => NotificationChannel::query()->orderBy('name')->get()
                ->map(fn (NotificationChannel $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type,
                    'enabled' => $c->enabled,
                    'config' => $this->publicConfig($c),
                ]),
            'adapters' => $registry->definitions(),
        ]);
    }

    public function store(ChannelRequest $request): RedirectResponse
    {
        $this->authorize('create', NotificationChannel::class);

        NotificationChannel::create($request->channelData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel added.')]);

        return back();
    }

    public function update(ChannelRequest $request, NotificationChannel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $data = $request->channelData();
        $data['config'] = $data['type'] === $channel->type ? [...$channel->config, ...$data['config']] : $data['config'];
        $channel->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel updated.')]);

        return back();
    }

    public function destroy(NotificationChannel $channel): RedirectResponse
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return back();
    }

    /**
     * Send a test message so the admin can verify the configuration.
     */
    public function test(NotificationChannel $channel, AdapterRegistry $registry): RedirectResponse
    {
        $this->authorize('update', $channel);

        try {
            $registry->make($channel->type)->send($channel->config, new AlertMessage('test', __('Nocline test notification'), __('If you read this, the channel works.')));
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Test notification sent.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Test failed: :error', ['error' => mb_substr($e->getMessage(), 0, 200)])]);
        }

        return back();
    }

    /**
     * Non-secret config values only (secrets are never sent to the browser).
     *
     * @return array<string, mixed>
     */
    private function publicConfig(NotificationChannel $channel): array
    {
        $registry = app(AdapterRegistry::class);

        if (! $registry->has($channel->type)) {
            return [];
        }

        $public = [];
        foreach ($registry->class($channel->type)::fields() as $field) {
            if (! $field->secret) {
                $public[$field->name] = $channel->config[$field->name] ?? null;
            }
        }

        return $public;
    }
}
