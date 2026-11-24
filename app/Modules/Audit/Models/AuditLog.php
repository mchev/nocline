<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use App\Modules\Audit\Policies\AuditLogPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the activity log: who did what, when, from where. Append-only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action dotted, see AuditAction
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label human name at the time, so deleted things stay readable
 * @property array<string, mixed>|null $meta small and never secret
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonInterface $created_at
 */
#[UsePolicy(AuditLogPolicy::class)]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
