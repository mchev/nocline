<?php

namespace App\Modules\Wallboard\Support;

/**
 * Opaque deploy marker for kiosk wallboards: when it changes, clients reload the HTML shell.
 */
final class HubRevision
{
    public function current(): string
    {
        $override = config('phare.deploy_revision');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $parts = [config('phare.version')];

        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $parts[] = (string) filemtime($manifest);
            $parts[] = substr(hash('xxh128', (string) file_get_contents($manifest)), 0, 12);
        }

        $compat = resource_path('views/wallboard/compat.blade.php');
        if (is_file($compat)) {
            $parts[] = 'compat:'.filemtime($compat);
        }

        return config('phare.version').'+'.substr(hash('xxh128', implode('|', $parts)), 0, 12);
    }
}
