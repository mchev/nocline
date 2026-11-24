<?php

namespace App\Modules\Alertes\Adapters;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Http;

/**
 * Transactional email through the Brevo API (no SMTP server needed).
 */
class BrevoAdapter implements NotificationAdapter
{
    public static function key(): string
    {
        return 'brevo';
    }

    public static function label(): string
    {
        return 'Brevo (transactional email)';
    }

    public static function fields(): array
    {
        return [
            new AdapterField('api_key', 'API key', 'password', secret: true),
            new AdapterField('sender_email', 'Sender address (verified in Brevo)', 'email'),
            new AdapterField('to', 'Recipient', 'email'),
        ];
    }

    public function send(array $config, AlertMessage $message): void
    {
        Http::timeout(10)
            ->withHeaders(['api-key' => $config['api_key']])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => ['name' => config('app.name'), 'email' => $config['sender_email']],
                'to' => [['email' => $config['to']]],
                'subject' => $message->subject,
                'textContent' => $message->body !== '' ? $message->subject."\n\n".$message->body : $message->subject,
            ])->throw();
    }
}
