<?php

namespace App\Modules\Checks\Models;

use App\Modules\Checks\Enums\CheckStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $check_id
 * @property CheckStatus $from_status
 * @property CheckStatus $to_status
 * @property CarbonInterface $at
 * @property array<string, mixed>|null $metadata
 */
class CheckEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => CheckStatus::class,
            'to_status' => CheckStatus::class,
            'at' => 'datetime',
            'metadata' => 'array',
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
