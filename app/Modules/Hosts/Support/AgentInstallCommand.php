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

    /** Refresh binary + restart systemd without rotating the API token. */
    public static function curlUpdateSh(): string
    {
        return 'curl -fsSL '.url('/install/agent-update.sh').' | sudo sh';
    }
}
