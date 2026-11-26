<?php

namespace App\Modules\Alertes\Services;

use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Support\AlertMessage;
use App\Modules\Incidents\Models\Incident;
use Throwable;

/**
 * Sends incident notifications to every enabled channel, idempotently per (incident, kind, channel).
 */
class AlertDispatcher
{
    public function __construct(
        private AdapterRegistry $adapters,
        private NotificationChannelResolver $channels,
        private SnoozedAlertGate $snooze,
        private NotificationPreferences $preferences,
    ) {}

    public function dispatch(Incident $incident, string $kind): void
    {
        $incident->loadMissing('check');

        if (! $this->preferences->incidentNotificationsEnabled($kind)) {
            return;
        }

        if ($incident->check === null || $this->snooze->isMuted($incident->check)) {
            return;
        }

        $this->channels->forCheck($incident->check)->each(function (NotificationChannel $channel) use ($incident, $kind): void {
            $delivery = AlertDelivery::query()->firstOrCreate(
                ['dedupe_key' => "incident:{$incident->id}:{$kind}:channel:{$channel->id}"],
                ['incident_id' => $incident->id, 'channel_id' => $channel->id, 'kind' => $kind, 'status' => 'pending'],
            );

            if (! $delivery->wasRecentlyCreated) {
                return;
            }

            try {
                $this->send($channel, $incident, $kind);
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $e) {
                $delivery->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        });
    }

    private function send(NotificationChannel $channel, Incident $incident, string $kind): void
    {
        $this->adapters->make($channel->type)->send($channel->config, AlertMessage::forIncident($incident, $kind === 'resolved' ? 'resolved' : 'opened'));
    }
}
