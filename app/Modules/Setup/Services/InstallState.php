<?php

namespace App\Modules\Setup\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Throwable;

class InstallState
{
    public function isInstalled(): bool
    {
        if (config('nocline.force_installed') || File::exists(config('nocline.installed_marker'))) {
            return true;
        }

        $defaultMarker = storage_path('app/nocline-installed');
        if (config('nocline.installed_marker') === $defaultMarker && File::exists(storage_path('app/phare-installed'))) {
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
        File::ensureDirectoryExists(dirname(config('nocline.installed_marker')));
        File::put(config('nocline.installed_marker'), now()->toIso8601String());
    }
}
