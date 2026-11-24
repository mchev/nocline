<?php

namespace App\Modules\Alertes\Models;

use App\Modules\Alertes\Policies\NotificationChannelPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $type adapter key (see AdapterRegistry)
 * @property array<string, mixed> $config
 * @property bool $enabled
 */
#[Fillable(['name', 'type', 'config', 'enabled'])]
#[Hidden(['config'])]
#[UsePolicy(NotificationChannelPolicy::class)]
class NotificationChannel extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }
}
