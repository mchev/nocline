<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\DB;

/**
 * Copies a SQLite database file before an update. Other engines are the operator's responsibility.
 */
class DatabaseBackup
{
    public function supported(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'sqlite';
    }

    /**
     * @return string|null path of the copy, or null when there is no database file to copy
     */
    public function copy(?string $connection = null): ?string
    {
        $database = (string) DB::connection($connection)->getDatabaseName();

        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            return null;
        }

        $directory = (string) config('nocline.backup_dir');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create {$directory}.");
        }

        $target = $directory.'/'.date('Ymd-His').'-'.basename($database);

        if (! copy($database, $target)) {
            throw new \RuntimeException("Could not copy the database to {$target}.");
        }

        return $target;
    }
}
