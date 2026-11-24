<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Hub-local execution for cheap checks (HTTP, TCP, agent presence).
 * ICMP/DNS are probe-only: never executed from PHP. Heartbeat checks are push-only (see HeartbeatService).
 */
class CheckExecutor
{
    /**
     * @return array{ok: bool, latency_ms: int|null, error: string|null, metadata: array<string, mixed>}|null null when the type is not executable by the hub
     */
    public function execute(Check $check): ?array
    {
        return match ($check->type) {
            CheckType::Http => $this->http($check),
            CheckType::Tcp => $this->tcp($check),
            CheckType::Agent => $this->agent($check),
            default => null,
        };
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, error: string|null, metadata: array<string, mixed>}
     */
    private function http(Check $check): array
    {
        $start = hrtime(true);

        try {
            $response = Http::timeout($check->timeout_sec)->withoutRedirecting()->get($check->target);
            $latency = (int) round((hrtime(true) - $start) / 1e6);
            $expected = $check->config['expected_status'] ?? null;
            $statusOk = $expected !== null
                ? $response->status() === (int) $expected
                : $response->status() < 400;
            $keyword = $check->config['keyword'] ?? null;
            $keywordOk = $keyword === null || str_contains($response->body(), $keyword);

            $error = match (true) {
                ! $statusOk => "HTTP {$response->status()}",
                ! $keywordOk => 'Keyword not found',
                default => null,
            };

            return [
                'ok' => $error === null,
                'latency_ms' => $latency,
                'error' => $error,
                'metadata' => ['http_status' => $response->status()],
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => null, 'error' => mb_substr($e->getMessage(), 0, 300), 'metadata' => []];
        }
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, error: string|null, metadata: array<string, mixed>}
     */
    private function tcp(Check $check): array
    {
        [$host, $port] = array_pad(explode(':', $check->target, 2), 2, null);
        $start = hrtime(true);
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, $check->timeout_sec);

        if ($socket === false) {
            return ['ok' => false, 'latency_ms' => null, 'error' => $errstr ?: 'Connection failed', 'metadata' => []];
        }

        fclose($socket);

        return ['ok' => true, 'latency_ms' => (int) round((hrtime(true) - $start) / 1e6), 'error' => null, 'metadata' => []];
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, error: string|null, metadata: array<string, mixed>}
     */
    private function agent(Check $check): array
    {
        $host = $check->host;
        $ok = $host !== null && $host->isOnline();

        return ['ok' => $ok, 'latency_ms' => null, 'error' => $ok ? null : 'Agent not seen recently', 'metadata' => []];
    }
}
