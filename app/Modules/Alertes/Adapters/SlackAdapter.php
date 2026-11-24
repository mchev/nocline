<?php

namespace App\Modules\Alertes\Adapters;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Http;

class SlackAdapter implements NotificationAdapter
{
    public static function key(): string
    {
        return 'slack';
    }

    public static function label(): string
    {
        return 'Slack';
    }

    public static function fields(): array
    {
        return [new AdapterField('webhook_url', 'Incoming webhook URL', 'url', secret: true, placeholder: 'https://hooks.slack.com/services/…')];
    }

    public function send(array $config, AlertMessage $message): void
    {
        $icon = match ($message->kind) {
            'opened' => ':red_circle:',
            'resolved' => ':white_check_mark:',
            default => ':bell:',
        };

        Http::timeout(10)->post($config['webhook_url'], ['text' => "{$icon} ".$message->plainText()])->throw();
    }
}
