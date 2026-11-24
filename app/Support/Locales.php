<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Locales are discovered from lang/*.json (key = English source string).
 * A contributor adds a language by dropping lang/<code>.json with a "_language" entry.
 */
class Locales
{
    public const DEFAULT = 'en';

    /**
     * @return array<string, string> code => native language name
     */
    public static function available(): array
    {
        $locales = [];

        foreach (File::glob(lang_path('*.json')) as $file) {
            $code = basename($file, '.json');
            $data = json_decode(File::get($file), true);

            if (is_array($data) && preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $code)) {
                $locales[$code] = (string) ($data['_language'] ?? $code);
            }
        }

        ksort($locales);

        return $locales;
    }

    public static function isAvailable(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::available());
    }

    /**
     * @return array<string, string>
     */
    public static function translations(string $code): array
    {
        $file = lang_path("{$code}.json");

        return File::exists($file) ? (array) json_decode(File::get($file), true) : [];
    }
}
