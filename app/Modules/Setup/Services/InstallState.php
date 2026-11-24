<?php

namespace App\Modules\Setup\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Throwable;

class InstallState
{
    public function isInstalled(): bool
    {
        if (config('phare.force_installed') || File::exists(config('phare.installed_marker'))) {
            return true;
        }

        // Instances created before the wizard existed: an existing user means "installed".
        try {
            return User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function markInstalled(): void
    {
        File::ensureDirectoryExists(dirname(config('phare.installed_marker')));
        File::put(config('phare.installed_marker'), now()->toIso8601String());
    }
}
