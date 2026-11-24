<?php

namespace App\Modules\Hosts\Support;

/**
 * One-liner served to operators: download agent, enroll, start systemd (see install/agent.blade.php).
 */
final class AgentInstallCommand
{
    public static function curlSh(string $enrollmentToken): string
    {
        return 'curl -fsSL '.url("/install/{$enrollmentToken}").' | sudo sh';
    }
}
