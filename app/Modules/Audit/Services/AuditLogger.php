<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the activity log. Auditing must never break the action being audited: failures are reported, not thrown.
 */
class AuditLogger
{
    public const MAX_META_ENTRIES = 20;

    public const MAX_STRING_LENGTH = 120;

    /** Meta keys holding anything that looks like a secret are dropped, whatever the caller passes. */
    private const SENSITIVE_KEY = '/pass|secret|token|key|cipher|credential|authorization|otp|recovery|notes?$|value|config/i';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(AuditAction|string $action, ?Model $subject = null, array $meta = [], ?User $actor = null, ?string $subjectLabel = null): ?AuditLog
    {
        try {
            $actor ??= Auth::user() instanceof User ? Auth::user() : null;
            $meta = $this->sanitize($meta);

            if ($actor !== null) {
                $meta['actor'] = Str::limit($actor->name, self::MAX_STRING_LENGTH, '');
            }

            return AuditLog::query()->create([
                'user_id' => $actor?->id,
                'action' => $action instanceof AuditAction ? $action->value : $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel ?? ($subject !== null ? $this->labelFor($subject) : null),
                'meta' => $meta === [] ? null : $meta,
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * The human name of a model at this moment (kept in the log so deleted things stay readable).
     */
    public function labelFor(Model $subject): ?string
    {
        foreach (['name', 'label', 'title'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 255, '');
            }
        }

        return null;
    }

    /**
     * Keep the meta small and harmless: scalars and short lists of scalars only, no sensitive keys.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, scalar|list<scalar>|null>
     */
    private function sanitize(array $meta): array
    {
        $clean = [];

        foreach (array_slice($meta, 0, self::MAX_META_ENTRIES, true) as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY, (string) $key) === 1) {
                continue;
            }

            if (is_array($value)) {
                $value = array_values(array_map(
                    fn (mixed $item): string => Str::limit((string) (is_scalar($item) ? $item : ''), self::MAX_STRING_LENGTH, ''),
                    array_slice($value, 0, self::MAX_META_ENTRIES),
                ));
            } elseif (is_string($value)) {
                $value = Str::limit($value, self::MAX_STRING_LENGTH, '');
            } elseif (! is_scalar($value) && $value !== null) {
                continue;
            }

            $clean[(string) $key] = $value;
        }

        return $clean;
    }
}
