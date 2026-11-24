<?php

namespace App\Modules\Coffre\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail: never contains secret material.
 *
 * @property int $id
 * @property int|null $vault_entry_id
 * @property string $entry_label
 * @property int|null $user_id
 * @property string $action view|copy|create|edit|delete
 * @property CarbonInterface $at
 * @property string|null $ip
 * @property string|null $user_agent
 */
class VaultAccessLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Vault audit log is append-only.'));
        static::deleting(fn () => throw new LogicException('Vault audit log is append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
