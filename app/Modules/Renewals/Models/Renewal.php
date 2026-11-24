<?php

namespace App\Modules\Renewals\Models;

use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Renewals\Policies\RenewalPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $check_id
 * @property string $kind domain|ssl|contract|other
 * @property string $source auto|manual
 * @property string $label
 * @property CarbonInterface $expires_on
 * @property array<int, int>|null $remind_days
 * @property string|null $notes
 * @property CarbonInterface|null $last_checked_at
 * @property string|null $check_error
 */
#[Fillable(['group_id', 'kind', 'label', 'expires_on', 'remind_days', 'notes'])]
#[UsePolicy(RenewalPolicy::class)]
class Renewal extends Model
{
    /** Default reminder thresholds, in days before expiry. */
    public const DEFAULT_REMIND_DAYS = [30, 14, 7, 1];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'last_checked_at' => 'datetime',
            'remind_days' => 'array',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function thresholds(): array
    {
        return $this->remind_days ?: self::DEFAULT_REMIND_DAYS;
    }

    public function daysRemaining(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_on->startOfDay(), false);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    /**
     * @return HasMany<RenewalReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(RenewalReminder::class);
    }
}
