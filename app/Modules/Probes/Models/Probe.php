<?php

namespace App\Modules\Probes\Models;

use App\Modules\Hosts\Concerns\HasAgentToken;
use App\Modules\Probes\Policies\ProbePolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $role primary|external
 * @property bool $enabled
 * @property string|null $enrollment_token_hash
 * @property CarbonInterface|null $enrollment_expires_at
 * @property string|null $api_token_hash
 * @property CarbonInterface|null $enrolled_at
 * @property CarbonInterface|null $last_seen_at
 * @property array<int, string>|null $capabilities
 * @property string|null $agent_version
 */
#[Fillable(['name', 'role', 'enabled'])]
#[Hidden(['enrollment_token_hash', 'api_token_hash'])]
#[UsePolicy(ProbePolicy::class)]
class Probe extends Model
{
    use HasAgentToken;

    public const ENROLLMENT_PREFIX = 'prb_';

    public const API_PREFIX = 'prp_';

    /** A probe not seen for this long no longer counts in quorum. */
    public const STALE_AFTER_MINUTES = 5;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'capabilities' => 'array',
            'enrollment_expires_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }
}
