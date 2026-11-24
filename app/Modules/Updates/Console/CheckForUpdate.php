<?php

namespace App\Modules\Updates\Console;

use App\Modules\Updates\Services\ReleaseChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:update:check')]
#[Description('Look for a newer Phare release on GitHub')]
class CheckForUpdate extends Command
{
    public function handle(ReleaseChecker $checker): int
    {
        $installed = (string) config('phare.version');

        if (! config('phare.update_check')) {
            $this->components->info('Update check is disabled (PHARE_UPDATE_CHECK=false).');

            return self::SUCCESS;
        }

        $release = $checker->refresh();

        if ($release === null) {
            $this->components->warn('Could not read the latest release (offline, or no release published yet).');

            return self::SUCCESS;
        }

        if (version_compare($release['version'], $installed, '>')) {
            $this->components->info("Version {$release['version']} is available (installed: {$installed}). Run scripts/update.sh");
        } else {
            $this->components->info("Phare {$installed} is up to date.");
        }

        return self::SUCCESS;
    }
}
