<?php

namespace App\Modules\Alertes\Adapters;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Http;

class WebhookAdapter implements NotificationAdapter
{
    public static function key(): string
    {
        return 'webhook';
    }

    public static function label(): string
    {
        return 'Generic webhook (JSON)';
    }

    public static function fields(): array
    {
        return [new AdapterField('url', 'URL', 'url', secret: true, help: 'Receives a JSON POST: text, kind, incident_id, check.')];
    }

    public function send(array $config, AlertMessage $message): void
    {
        Http::timeout(10)->post($config['url'], [
            'text' => $message->plainText(),
            'kind' => $message->kind,
            'incident_id' => $message->incident?->id,
            'check' => $message->incident?->check?->name,
        ])->throw();
    }
}
