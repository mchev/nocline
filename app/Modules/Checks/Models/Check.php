<?php

namespace App\Modules\Checks\Models;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Policies\CheckPolicy;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use Carbon\CarbonInterface;
use Database\Factories\CheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $host_id
 * @property string $name
 * @property CheckType $type
 * @property string $target
 * @property int $interval_sec
 * @property int $timeout_sec
 * @property int $failure_threshold
 * @property array<string, mixed>|null $config
 * @property CheckCategory $category
 * @property array<int, string>|null $tags
 * @property bool $enabled
 * @property CheckExecution $execution
 * @property string|null $heartbeat_token
 * @property string $quorum any|majority|all
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['group_id', 'host_id', 'name', 'type', 'target', 'interval_sec', 'timeout_sec', 'failure_threshold', 'config', 'enabled', 'execution', 'quorum', 'category', 'tags'])]
#[Hidden(['heartbeat_token'])]
#[UseFactory(CheckFactory::class)]
#[UsePolicy(CheckPolicy::class)]
class Check extends Model
{
    /** @use HasFactory<CheckFactory> */
    use HasFactory;

    /** Length of the random public token of a heartbeat check. */
    public const HEARTBEAT_TOKEN_LENGTH = 40;

    protected static function booted(): void
    {
        static::saving(function (Check $check): void {
            if ($check->type !== CheckType::Heartbeat) {
                return;
            }

            $check->heartbeat_token ??= Str::random(self::HEARTBEAT_TOKEN_LENGTH);
            $check->failure_threshold = 1;
        });

        static::created(function (Check $check): void {
            $check->state()->firstOrCreate([], ['next_run_at' => now()]);
            CheckCreated::dispatch($check);
        });
    }

    protected function casts(): array
    {
        return [
            'type' => CheckType::class,
            'category' => CheckCategory::class,
            'execution' => CheckExecution::class,
            'tags' => 'array',
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /**
     * ICMP/DNS can only run on probes; others follow the chosen execution mode.
     */
    public function runsOnProbes(): bool
    {
        return $this->execution === CheckExecution::Probe || in_array($this->type, [CheckType::Icmp, CheckType::Dns], true);
    }

    /**
     * Seconds a heartbeat may be late (after its expected interval) before it counts as missed:
     * the configured `grace_sec`, else 10% of the interval with a 60 s floor.
     */
    public function heartbeatGraceSeconds(): int
    {
        $configured = $this->config['grace_sec'] ?? null;

        return $configured !== null ? (int) $configured : max(60, (int) round($this->interval_sec / 10));
    }

    /**
     * The public URL the monitored job pings (null for other check types).
     */
    public function heartbeatUrl(): ?string
    {
        return $this->heartbeat_token !== null ? url("/api/v1/heartbeat/{$this->heartbeat_token}") : null;
    }

    /**
     * @return HasOne<CheckState, $this>
     */
    public function state(): HasOne
    {
        return $this->hasOne(CheckState::class);
    }

    /**
     * @return HasMany<CheckEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CheckEvent::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
