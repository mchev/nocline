<?php

namespace App\Modules\Hosts\Support;

final class HostStackRules
{
    /** @var list<string> */
    public const KEYS = [
        'php',
        'node',
        'mysql',
        'mariadb',
        'postgresql',
        'redis',
        'nginx',
        'docker',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(string $prefix = 'host_meta.stack'): array
    {
        $rules = [
            $prefix => ['nullable', 'array'],
        ];

        foreach (self::KEYS as $key) {
            $rules[$prefix === '' ? $key : "{$prefix}.{$key}"] = [
                'nullable',
                'string',
                'max:32',
                'regex:/^[0-9A-Za-z.+~\-]+$/',
            ];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>|null  $incoming
     * @return array<string, string>|null
     */
    public static function normalize(?array $incoming): ?array
    {
        if ($incoming === null || $incoming === []) {
            return null;
        }

        $out = [];

        foreach (self::KEYS as $key) {
            $value = $incoming[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out === [] ? null : $out;
    }
}
