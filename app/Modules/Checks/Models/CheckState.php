<?php

namespace App\Modules\Checks\Models;

use App\Modules\Checks\Enums\CheckStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current state of a check: one row per check, updated in place.
 *
 * @property int $check_id
 * @property CheckStatus $status
 * @property CarbonInterface|null $since_at
 * @property CarbonInterface|null $last_run_at
 * @property int|null $last_latency_ms
 * @property string|null $last_error
 * @property int $consecutive_failures
 * @property CarbonInterface|null $next_run_at
 * @property CarbonInterface|null $last_ping_at
 */
class CheckState extends Model
{
    protected $primaryKey = 'check_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CheckStatus::class,
            'since_at' => 'datetime',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_ping_at' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'unknown', 'consecutive_failures' => 0];

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
