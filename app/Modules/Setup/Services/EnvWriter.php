<?php

namespace App\Modules\Setup\Services;

use Illuminate\Support\Facades\File;

/**
 * Minimal, safe .env updater (adds or replaces keys, preserves everything else).
 */
class EnvWriter
{
    /**
     * @param  array<string, string|null>  $values
     */
    public function write(array $values, ?string $path = null): void
    {
        $path ??= config('nocline.env_path');
        $contents = File::exists($path) ? File::get($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        File::put($path, $contents);
    }

    private function format(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@\-]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }
}
