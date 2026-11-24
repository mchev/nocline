<?php

namespace App\Modules\Hosts\Models;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Concerns\HasAgentToken;
use App\Modules\Hosts\Policies\HostPolicy;
use Carbon\CarbonInterface;
use Database\Factories\HostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $group_id
 * @property string $name
 * @property array<int, string>|null $tags
 * @property string|null $enrollment_token_hash
 * @property CarbonInterface|null $enrollment_expires_at
 * @property string|null $api_token_hash
 * @property CarbonInterface|null $enrolled_at
 * @property CarbonInterface|null $last_seen_at
 * @property string|null $agent_version
 * @property string|null $os
 * @property string|null $color
 * @property array<string, mixed>|null $agent_meta
 * @property CarbonInterface|null $agent_meta_at
 */
#[Fillable(['group_id', 'name', 'tags', 'color'])]
#[Hidden(['enrollment_token_hash', 'api_token_hash'])]
#[UseFactory(HostFactory::class)]
#[UsePolicy(HostPolicy::class)]
class Host extends Model
{
    /** @use HasFactory<HostFactory> */
    use HasAgentToken, HasFactory;

    public const ENROLLMENT_PREFIX = 'phe_';

    public const API_PREFIX = 'pha_';

    /** Minutes without a push before a host is considered offline. */
    public const OFFLINE_AFTER_MINUTES = 3;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'agent_meta' => 'array',
            'enrollment_expires_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'agent_meta_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::OFFLINE_AFTER_MINUTES));
    }

    /**
     * @return HasOne<Check, $this>
     */
    public function agentCheck(): HasOne
    {
        return $this->hasOne(Check::class)->where('type', CheckType::Agent);
    }

    /**
     * Monitors explicitly linked to this server (sites, ports, etc.), excluding the agent presence check.
     *
     * @return HasMany<Check, $this>
     */
    public function linkedChecks(): HasMany
    {
        return $this->hasMany(Check::class)->where('type', '!=', CheckType::Agent);
    }
}
