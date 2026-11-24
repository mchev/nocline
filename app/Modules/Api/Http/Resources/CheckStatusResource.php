<?php

namespace App\Modules\Api\Http\Resources;

use App\Modules\Checks\Models\Check;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Current state of a check (one row, updated in place).
 *
 * @mixin Check
 */
class CheckStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state;

        return [
            'check_id' => $this->id,
            'status' => $state?->status->value ?? 'unknown',
            'since_at' => $state?->since_at?->toIso8601String(),
            'last_checked_at' => $state?->last_run_at?->toIso8601String(),
            'last_ping_at' => $state?->last_ping_at?->toIso8601String(),
            'latency_ms' => $state?->last_latency_ms,
            'last_error' => $state?->last_error,
            'consecutive_failures' => $state?->consecutive_failures,
        ];
    }
}
