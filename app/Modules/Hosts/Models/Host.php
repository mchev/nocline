<?php

namespace App\Modules\Hosts\Models;

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
 */
#[Fillable(['group_id', 'name', 'tags'])]
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
            'enrollment_expires_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'last_seen_at' => 'datetime',
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
}
