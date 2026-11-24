<?php

namespace App\Modules\Checks\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $check_id
 * @property CarbonInterface $at
 * @property int|null $latency_ms
 * @property bool $ok
 */
class CheckLatencySample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'at' => 'datetime',
            'latency_ms' => 'integer',
            'ok' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
