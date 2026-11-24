<?php

namespace App\Modules\Alertes\Contracts;

use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;

/**
 * A notification adapter delivers an AlertMessage to one external system (email, Slack, Brevo, WhatsApp…).
 *
 * To add one, create a class implementing this contract in app/Modules/Alertes/Adapters/:
 * it is discovered automatically and appears in the "Alerts" page with the form fields it declares.
 * See docs/contributing/notification-adapters.md.
 */
interface NotificationAdapter
{
    /**
     * Stable identifier stored in the database (lowercase, e.g. "slack"). Never change it once released.
     */
    public static function key(): string;

    /**
     * Human readable name shown in the UI (an English source string; it is translated with __()).
     */
    public static function label(): string;

    /**
     * Configuration fields asked from the admin. Their names become keys of the channel config
     * (stored encrypted); do not use the reserved names "name", "type" and "enabled".
     *
     * @return array<int, AdapterField>
     */
    public static function fields(): array;

    /**
     * Deliver the message. Throw any exception on failure: the delivery is then recorded as failed
     * and other channels are not affected. Use short timeouts (≤ 10 s).
     *
     * @param  array<string, mixed>  $config  values of the declared fields
     */
    public function send(array $config, AlertMessage $message): void;
}
