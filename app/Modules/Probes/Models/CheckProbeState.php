<?php

namespace App\Modules\Probes\Models;

use App\Modules\Checks\Models\Check;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Latest result of one probe for one check (updated in place, no history).
 *
 * @property int $id
 * @property int $check_id
 * @property int $probe_id
 * @property CarbonInterface|null $next_run_at
 * @property CarbonInterface|null $last_run_at
 * @property bool|null $last_ok
 * @property int|null $last_latency_ms
 * @property string|null $last_error
 */
class CheckProbeState extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'last_ok' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    /**
     * @return BelongsTo<Probe, $this>
     */
    public function probe(): BelongsTo
    {
        return $this->belongsTo(Probe::class);
    }
}
