<?php

namespace App\Modules\Api\Http\Resources;

use App\Modules\Checks\Models\Check;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Check
 */
class CheckResource extends JsonResource
{
    /**
     * Expects the `state` relation to be loaded. The heartbeat URL embeds a secret, so only
     * users allowed to manage checks receive it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'category' => $this->category->value,
            'target' => $this->target,
            'group_id' => $this->group_id,
            'host_id' => $this->host_id,
            'tags' => $this->tags ?? [],
            'interval_sec' => $this->interval_sec,
            'timeout_sec' => $this->timeout_sec,
            'failure_threshold' => $this->failure_threshold,
            'config' => $this->config,
            'enabled' => $this->enabled,
            'execution' => $this->execution->value,
            'quorum' => $this->quorum,
            'status' => $this->state?->status->value ?? 'unknown',
            'since_at' => $this->state?->since_at?->toIso8601String(),
            'heartbeat_url' => $this->when($request->user()?->canManage(), fn () => $this->heartbeatUrl()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
