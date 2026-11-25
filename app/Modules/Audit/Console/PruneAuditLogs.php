<?php

namespace App\Modules\Audit\Console;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:audit:prune {--days= : Keep this many days (default: config nocline.audit_retention_days)}')]
#[Description('Delete activity log entries older than the retention period')]
class PruneAuditLogs extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('nocline.audit_retention_days')));

        $deleted = AuditLog::query()->where('created_at', '<', now()->subDays($days))->delete();

        $this->components->info("Deleted {$deleted} activity log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
