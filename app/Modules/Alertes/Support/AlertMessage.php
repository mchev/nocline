<?php

namespace App\Modules\Alertes\Support;

use App\Modules\Incidents\Models\Incident;

/**
 * What an adapter has to deliver. Incident messages carry the incident for rich rendering.
 */
final readonly class AlertMessage
{
    /**
     * @param  'opened'|'resolved'|'reminder'|'test'  $kind
     */
    public function __construct(
        public string $kind,
        public string $subject,
        public string $body = '',
        public ?Incident $incident = null,
    ) {}

    /**
     * @param  'opened'|'resolved'  $kind
     */
    public static function forIncident(Incident $incident, string $kind): self
    {
        return new self(
            $kind,
            ($kind === 'resolved' ? __('RESOLVED') : __('DOWN')).': '.$incident->title,
            (string) $incident->last_error,
            $incident,
        );
    }

    /**
     * Single-string rendering for chat-like systems.
     */
    public function plainText(): string
    {
        if ($this->incident !== null) {
            return $this->subject.($this->body !== '' ? " ({$this->body})" : '');
        }

        return $this->body !== '' ? "{$this->subject}\n{$this->body}" : $this->subject;
    }
}
