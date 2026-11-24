<?php

namespace App\Modules\Metrics\Models;

use App\Modules\Hosts\Models\Host;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $host_id
 * @property string $metric_key
 * @property int $bucket_size
 * @property CarbonInterface $bucket_start
 * @property float|null $min
 * @property float|null $max
 * @property float $avg
 * @property float|null $p95
 * @property int $sample_count
 */
class MetricRollup extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Bucket sizes in seconds. */
    public const BUCKET_1M = 60;

    public const BUCKET_5M = 300;

    public const BUCKET_1H = 3600;

    protected function casts(): array
    {
        return ['bucket_start' => 'datetime'];
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
