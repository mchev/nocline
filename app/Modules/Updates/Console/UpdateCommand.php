<?php

namespace App\Modules\Updates\Console;

use App\Modules\Updates\Services\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Everything that must happen once the new code is in place (scripts/update.sh fetches it):
 * back up, maintenance mode, migrate, refresh caches, restart workers. Never resets data.
 */
#[Signature('phare:update {--skip-backup : Do not back up the database (you did it yourself)}')]
#[Description('Finish an update: back up, migrate, refresh caches, restart workers')]
class UpdateCommand extends Command
{
    public function handle(DatabaseBackup $backup): int
    {
        if (! $this->option('skip-backup') && ! $this->backUp($backup)) {
            return self::FAILURE;
        }

        $this->callSilently('down', ['--retry' => 30]);

        try {
            $this->components->task('Applying database migrations', fn () => $this->call('migrate', ['--force' => true]) === 0);
            $this->components->task('Refreshing caches', fn () => $this->callSilently('optimize:clear') === 0 && $this->callSilently('optimize') === 0);
            $this->components->task('Restarting workers', fn () => $this->callSilently('queue:restart') === 0);
        } catch (Throwable $e) {
            $this->components->error('Update failed: '.$e->getMessage());
            $this->components->warn('Your data was not modified beyond the migrations that completed. Restore the backup if needed.');

            return self::FAILURE;
        } finally {
            $this->callSilently('up');
        }

        $this->components->info('Phare is now running version '.config('phare.version').'.');

        return self::SUCCESS;
    }

    /**
     * SQLite: copy the file. Other databases: ask the operator to confirm they have a backup.
     */
    private function backUp(DatabaseBackup $backup): bool
    {
        if (! $backup->supported()) {
            if ($this->confirm('Your database is not SQLite and cannot be copied automatically. Do you have a recent backup?', false)) {
                return true;
            }

            $this->components->error('Back up your database first, then run again (or use --skip-backup).');

            return false;
        }

        try {
            $path = $backup->copy();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        if ($path !== null) {
            $this->components->info("Database backed up to {$path}");
        }

        return true;
    }
}
