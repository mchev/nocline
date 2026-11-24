<?php

namespace App\Modules\Checks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\HeartbeatService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public endpoint pinged by scheduled jobs. The token is the only credential; the response is
 * "ok" for a known token and an identical empty 404 for anything else, so nothing else leaks.
 */
class HeartbeatController extends Controller
{
    public function __invoke(Request $request, HeartbeatService $heartbeats, string $token, ?string $outcome = null): Response
    {
        $check = preg_match('/^[A-Za-z0-9]{'.Check::HEARTBEAT_TOKEN_LENGTH.'}$/', $token) === 1
            ? Check::query()->where('heartbeat_token', $token)->where('type', CheckType::Heartbeat)->first()
            : null;

        abort_if($check === null, 404);

        if ($check->enabled) {
            $failed = $outcome === 'fail' || $request->input('status') === 'fail';
            $heartbeats->ping($check, ! $failed, $request->input('msg'));
        }

        return response('ok', 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
