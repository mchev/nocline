<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Services\CheckStatusHistory;

/**
 * Time range and resolution for uptime history bars.
 */
final readonly class HistoryWindow
{
    public function __construct(
        public int $buckets,
        public float $hours,
        public bool $imputePreCreation = false,
    ) {}

    public function bucketSeconds(): int
    {
        return max(60, (int) round($this->hours * 3600 / $this->buckets));
    }

    public static function standard(): self
    {
        return new self(CheckStatusHistory::BUCKETS, (float) CheckStatusHistory::HOURS);
    }

    /** One dot per minute over the last 24 minutes — dashboard uptime strip. */
    public static function dashboard(): self
    {
        return new self(24, 24 / 60, imputePreCreation: false);
    }
}
