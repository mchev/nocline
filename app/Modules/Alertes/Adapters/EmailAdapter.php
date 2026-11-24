<?php

namespace App\Modules\Alertes\Adapters;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use App\Modules\Alertes\Notifications\IncidentMail;
use App\Modules\Alertes\Notifications\MessageMail;
use App\Modules\Alertes\Support\AdapterField;
use App\Modules\Alertes\Support\AlertMessage;
use Illuminate\Support\Facades\Mail;

class EmailAdapter implements NotificationAdapter
{
    public static function key(): string
    {
        return 'email';
    }

    public static function label(): string
    {
        return 'Email (SMTP)';
    }

    public static function fields(): array
    {
        return [new AdapterField('to', 'Recipient', 'email')];
    }

    public function send(array $config, AlertMessage $message): void
    {
        Mail::to($config['to'])->send(
            $message->incident !== null && in_array($message->kind, ['opened', 'resolved'], true)
                ? new IncidentMail($message->incident, $message->kind)
                : new MessageMail($message->subject, $message->body),
        );
    }
}
