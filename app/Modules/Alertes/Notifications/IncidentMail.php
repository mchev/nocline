<?php

namespace App\Modules\Alertes\Notifications;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class IncidentMail extends Mailable
{
    public function __construct(public Incident $incident, public string $kind) {}

    public function envelope(): Envelope
    {
        $prefix = $this->kind === 'resolved' ? __('[RESOLVED]') : __('[DOWN]');

        return new Envelope(subject: "{$prefix} {$this->incident->title}");
    }

    public function content(): Content
    {
        return new Content(text: 'alertes.incident');
    }
}
