<?php

namespace App\Modules\Alertes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $incident_id
 * @property int $channel_id
 * @property string $kind
 * @property string $dedupe_key
 * @property string $status
 * @property string|null $error
 */
class AlertDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
