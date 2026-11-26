<?php

namespace App\Modules\Hosts\Support;

/**
 * Parses version strings reported by the agent (distro suffixes allowed).
 */
final class StackVersion
{
    /**
     * @return array{major: int, minor: int, patch: int}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $raw, $matches)) {
            if (preg_match('/^(\d+)\b/', $raw, $majorOnly)) {
                return [
                    'major' => (int) $majorOnly[1],
                    'minor' => 0,
                    'patch' => 0,
                ];
            }

            return null;
        }

        $patch = array_key_exists(3, $matches) ? (int) $matches[3] : 0;

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => $patch,
        ];
    }

    public static function isOlderThan(string $installed, string $reference): bool
    {
        $left = self::parse($installed);
        $right = self::parse($reference);

        if ($left === null || $right === null) {
            return version_compare($installed, $reference, '<');
        }

        foreach (['major', 'minor', 'patch'] as $part) {
            if ($left[$part] !== $right[$part]) {
                return $left[$part] < $right[$part];
            }
        }

        return false;
    }
}
