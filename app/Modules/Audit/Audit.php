<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Convenience entry point: Audit::record('vault.revealed', $entry). Never throws.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $meta  small and never secret (no passwords, tokens or vault values)
     */
    public static function record(AuditAction|string $action, ?Model $subject = null, array $meta = []): void
    {
        app(AuditLogger::class)->record($action, $subject, $meta);
    }
}
