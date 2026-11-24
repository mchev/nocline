<?php

namespace App\Modules\Incidents\Models;

use App\Models\User;
use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Policies\IncidentPolicy;
use Carbon\CarbonInterface;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $check_id
 * @property string $title
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property CarbonInterface|null $resolved_at
 * @property string|null $last_error
 */
#[UseFactory(IncidentFactory::class)]
#[UsePolicy(IncidentPolicy::class)]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Incident>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
