<?php

namespace App\Modules\Alertes\Services;

use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Support\AlertMessage;
use Throwable;

/**
 * Generic one-off notification on every enabled channel (used by renewals reminders).
 */
class MessageNotifier
{
    public function __construct(private AdapterRegistry $adapters) {}

    /**
     * @return int number of channels successfully notified
     */
    public function broadcast(string $subject, string $body): int
    {
        $sent = 0;
        $message = new AlertMessage('reminder', $subject, $body);

        NotificationChannel::query()->where('enabled', true)->each(function (NotificationChannel $channel) use ($message, &$sent): void {
            try {
                $this->adapters->make($channel->type)->send($channel->config, $message);
                $sent++;
            } catch (Throwable) {
                // one failing channel must not block the others
            }
        });

        return $sent;
    }
}
