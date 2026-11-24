<?php

namespace App\Modules\Alertes\Notifications;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MessageMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'alertes.message');
    }
}
