<?php

namespace App\Modules\Maintenance\Models;

use App\Models\User;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Policies\MaintenanceWindowPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\MaintenanceWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planned intervention during which no incident is opened and no alert is sent.
 * Scope: one monitor, a group (and its sub-groups) or, when neither is set, everything.
 *
 * @property int $id
 * @property string $name
 * @property int|null $group_id
 * @property int|null $check_id
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property MaintenanceRecurrence $recurrence
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'group_id', 'check_id', 'starts_at', 'ends_at', 'recurrence', 'created_by'])]
#[UseFactory(MaintenanceWindowFactory::class)]
#[UsePolicy(MaintenanceWindowPolicy::class)]
class MaintenanceWindow extends Model
{
    /** @use HasFactory<MaintenanceWindowFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence' => MaintenanceRecurrence::class,
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Check, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The occurrence containing $at (or the next one after it), as [start, end]; null when it
     * is over for good. A recurring window repeats at the same local time-of-day in the app
     * timezone: occurrence k is [start + k*period, end + k*period] for k >= 0.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function occurrenceAt(CarbonInterface $at): ?array
    {
        $start = CarbonImmutable::instance($this->starts_at);
        $end = CarbonImmutable::instance($this->ends_at);
        $period = $this->recurrence->periodDays();

        if ($period === 0) {
            return $end >= $at ? [$start, $end] : null;
        }

        $tz = config('app.timezone');
        $localStart = $start->setTimezone($tz);
        $localEnd = $end->setTimezone($tz);
        $first = max(0, (int) floor($start->diffInDays($at, false) / $period) - 1);

        for ($k = $first; $k <= $first + 3; $k++) {
            $occurrence = [
                $localStart->addDays($k * $period)->utc(),
                $localEnd->addDays($k * $period)->utc(),
            ];

            if ($occurrence[1] >= $at) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Whether the window is in progress at $at.
     */
    public function isActiveAt(CarbonInterface $at): bool
    {
        $occurrence = $this->occurrenceAt($at);

        return $occurrence !== null && $occurrence[0] <= $at;
    }
}
