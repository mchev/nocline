<?php

namespace App\Modules\Alertes\Adapters;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Http;

class DiscordAdapter implements NotificationAdapter
{
    public static function key(): string
    {
        return 'discord';
    }

    public static function label(): string
    {
        return 'Discord';
    }

    public static function fields(): array
    {
        return [new AdapterField('webhook_url', 'Webhook URL', 'url', secret: true, placeholder: 'https://discord.com/api/webhooks/…')];
    }

    public function send(array $config, AlertMessage $message): void
    {
        Http::timeout(10)->post($config['webhook_url'], ['content' => mb_substr($message->plainText(), 0, 1900)])->throw();
    }
}
