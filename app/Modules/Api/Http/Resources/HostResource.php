<?php

namespace App\Modules\Api\Http\Resources;

use App\Modules\Hosts\Models\Host;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Host
 */
class HostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group_id' => $this->group_id,
            'tags' => $this->tags ?? [],
            'enrolled' => $this->enrolled_at !== null,
            'online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'agent_version' => $this->agent_version,
            'os' => $this->os,
        ];
    }
}
