<?php

namespace App\Modules\Wallboard\Support;

/**
 * Opaque deploy marker for kiosk wallboards: when it changes, clients reload the HTML shell.
 */
final class HubRevision
{
    public function current(): string
    {
        $override = config('nocline.deploy_revision');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $parts = [config('nocline.version')];

        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $parts[] = (string) filemtime($manifest);
            $parts[] = substr(hash('xxh128', (string) file_get_contents($manifest)), 0, 12);
        }

        return config('nocline.version').'+'.substr(hash('xxh128', implode('|', $parts)), 0, 12);
    }
}
