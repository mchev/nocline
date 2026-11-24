<?php

namespace App\Modules\Hosts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hosts\Models\Host;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the one-line server installer: `curl -fsSL <hub>/install/<token> | sudo sh`.
 * The one-time enrollment token in the URL is the only credential; it is useless once consumed.
 */
class InstallController extends Controller
{
    public function script(string $token): Response
    {
        $valid = Host::query()
            ->where('enrollment_token_hash', Host::hashToken($token))
            ->where('enrollment_expires_at', '>', now())
            ->exists();

        $body = $valid
            ? "#!/bin/sh\n".view('install.agent', ['hub' => escapeshellarg(rtrim(url('/'), '/')), 'token' => escapeshellarg($token)])->render()
            : "#!/bin/sh\necho 'This install link has expired. Open Phare and generate a new command.' >&2\nexit 1\n";

        return response($body, 200, ['Content-Type' => 'text/x-shellscript; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public function binary(string $arch): BinaryFileResponse|RedirectResponse|Response
    {
        $arch = str_replace('linux-', '', $arch);
        abort_unless(in_array($arch, ['amd64', 'arm64'], true), 404);

        $file = rtrim((string) config('phare.agent_binary_dir'), '/')."/phare-agent-linux-{$arch}";

        if (is_file($file)) {
            return response()->download($file, 'phare-agent', ['Content-Type' => 'application/octet-stream']);
        }

        if ($fallback = config('phare.agent_download_url')) {
            return redirect()->away(str_replace('{arch}', $arch, (string) $fallback));
        }

        return response('The agent binary is not available on this hub yet (run scripts/build-agent.sh).', 404);
    }
}
