<?php

namespace App\Console\Commands\Nocline;

use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Checks\Services\HeartbeatService;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Services\MetricIngestor;
use App\Modules\Probes\Models\Probe;
use App\Support\OpsBroadcast;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('nocline:demo:simulate {--interval=5 : Seconds between ticks} {--once : Run a single tick then exit} {--chaos : Extra flapping, latency spikes, agent gaps and metric stress}')]
#[Description('Simulate live agents (metrics), probes, heartbeats, synthetic check results and optional chaos for the demo dataset')]
class SimulateDemoCommand extends Command
{
    /** @var list<string> */
    private array $tickNotes = [];

    public function handle(MetricIngestor $ingestor, HeartbeatService $heartbeats, CheckResultRecorder $recorder): int
    {
        $path = storage_path('app/'.DemoSeeder::MANIFEST);

        if (! File::exists($path)) {
            $this->components->error('No demo manifest. Run: php artisan nocline:demo:seed');

            return self::FAILURE;
        }

        /** @var array{hosts?: array<string, string>, probes?: array<string, string>, heartbeats?: array<string, string>} $manifest */
        $manifest = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $interval = max(2, (int) $this->option('interval'));
        $chaos = (bool) $this->option('chaos');
        $tick = 0;

        $this->components->info('Simulating demo traffic'.($this->option('once') ? ' (once)' : " every {$interval}s").($chaos ? ' with chaos' : '').'. Ctrl+C to stop.');

        do {
            $tick++;
            $started = microtime(true);
            $this->tickNotes = [];

            try {
                $this->tickHosts($ingestor, $manifest['hosts'] ?? [], $tick, $chaos);
                $this->tickProbes($manifest['probes'] ?? [], $tick, $chaos);
                $this->tickHeartbeats($heartbeats, $manifest['heartbeats'] ?? [], $tick, $chaos);
                $this->tickAgentChecks($recorder);
                $this->tickSyntheticMonitors($recorder, $tick, $chaos);

                if ($chaos) {
                    $this->tickChaos($recorder, $tick);
                }

                OpsBroadcast::ping(1);
            } catch (Throwable $e) {
                $this->components->error("Tick #{$tick} failed: ".$e->getMessage());
            }

            $extra = $this->tickNotes !== [] ? ' · '.implode(' · ', array_slice($this->tickNotes, 0, 4)) : '';
            $this->line(sprintf(
                '[%s] tick #%d%s',
                now()->format('H:i:s'),
                $tick,
                $extra,
            ));

            if ($this->option('once')) {
                break;
            }

            $sleep = max(1, $interval - (int) round(microtime(true) - $started));
            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function tickHosts(MetricIngestor $ingestor, array $tokens, int $tick, bool $chaos): void
    {
        foreach ($tokens as $key => $token) {
            $host = Host::findByApiToken($token);

            if ($host === null) {
                $this->warn("Unknown host token for {$key}");

                continue;
            }

            if ($chaos && $key === 'web-1' && $tick % 24 >= 18 && $tick % 24 < 21) {
                $this->note('web-1 agent gap');

                continue;
            }

            [$cpu, $mem, $disk] = match ($key) {
                'db-1' => [
                    28 + ($tick % 4) * 4 + random_int(0, 6),
                    72 + ($tick % 6) + random_int(0, 4),
                    min(96, 78 + intdiv($tick, 6)),
                ],
                default => [
                    12 + ($tick % 9) * 2 + random_int(0, 10),
                    38 + ($tick % 7) * 2 + random_int(0, 5),
                    52 + ($tick % 5),
                ],
            };

            if ($chaos && $tick % 13 === 0 && $key === 'db-1') {
                $cpu = random_int(88, 99);
                $mem = random_int(85, 97);
                $this->note('db-1 metric spike');
            }

            $ingestor->ingest($host, [
                ['key' => 'cpu.usage_pct', 'avg' => $cpu, 'min' => $cpu - 2, 'max' => $cpu + 4, 'p95' => $cpu + 3],
                ['key' => 'mem.used_pct', 'avg' => $mem, 'min' => $mem - 1, 'max' => $mem + 2, 'p95' => $mem + 1],
                ['key' => 'disk.used_pct', 'avg' => $disk, 'min' => $disk, 'max' => $disk, 'p95' => $disk],
                ['key' => 'load.1', 'avg' => round($cpu / 25, 2)],
            ]);

            $host->forceFill([
                'last_seen_at' => now(),
                'agent_version' => '0.1.0-demo',
                'os' => $host->os ?: 'linux',
            ])->save();
        }
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function tickProbes(array $tokens, int $tick, bool $chaos): void
    {
        foreach ($tokens as $role => $token) {
            $probe = Probe::findByApiToken($token);

            if ($probe === null) {
                continue;
            }

            if ($chaos && $role === 'external' && $tick % 31 === 0) {
                $probe->forceFill(['last_seen_at' => now()->subMinutes(4)])->save();
                $this->note('external probe stale');

                continue;
            }

            $probe->forceFill([
                'last_seen_at' => now()->subSeconds($chaos ? random_int(0, 8) : 3),
                'agent_version' => '0.1.0-demo',
                'capabilities' => ['http', 'tcp', 'icmp', 'dns'],
            ])->save();
        }
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function tickHeartbeats(HeartbeatService $heartbeats, array $tokens, int $tick, bool $chaos): void
    {
        foreach ($tokens as $key => $token) {
            $check = Check::query()
                ->where('type', CheckType::Heartbeat)
                ->where('heartbeat_token', $token)
                ->first();

            if ($check === null) {
                continue;
            }

            $skip = match (true) {
                $chaos && $key === 'backup' && $tick % 7 === 0 => true,
                ! $chaos && $key === 'backup' && $tick % 12 === 0 => true,
                default => false,
            };

            if ($skip) {
                $this->note("heartbeat {$key} skipped");

                continue;
            }

            $ok = ! ($chaos && $key === 'cron' && $tick % 23 === 0);

            $heartbeats->ping(
                $check,
                $ok,
                $ok ? null : 'Cron reported failure (demo chaos)',
            );
        }

        Check::query()
            ->where('type', CheckType::Heartbeat)
            ->whereJsonContains('tags', 'demo')
            ->each(function (Check $check) use ($heartbeats): void {
                if ($heartbeats->failIfOverdue($check)) {
                    $this->note("heartbeat overdue: {$check->name}");
                }
            });
    }

    private function tickAgentChecks(CheckResultRecorder $recorder): void
    {
        Check::query()
            ->where('type', CheckType::Agent)
            ->whereJsonContains('tags', 'demo')
            ->with('host')
            ->each(function (Check $check) use ($recorder): void {
                $online = $check->host?->isOnline() ?? false;
                $recorder->record(
                    $check,
                    $online,
                    latencyMs: $online ? random_int(5, 40) : null,
                    error: $online ? null : 'Agent not seen recently',
                    metadata: ['source' => 'demo-simulate'],
                );
            });
    }

    private function tickSyntheticMonitors(CheckResultRecorder $recorder, int $tick, bool $chaos): void
    {
        Check::query()
            ->whereJsonContains('tags', 'demo')
            ->whereIn('type', [CheckType::Http, CheckType::Tcp, CheckType::Icmp, CheckType::Dns])
            ->each(function (Check $check) use ($recorder, $tick, $chaos): void {
                $ok = true;
                $latency = random_int(40, 180);
                $error = null;

                if ($check->name === 'Demo flaky.acme.test') {
                    $ok = ($tick % 6) < 3;
                    $error = $ok ? null : 'HTTP 503 (simulated flakiness)';
                    $latency = $ok ? random_int(80, 400) : null;
                }

                if ($check->name === 'Demo shop.acme.test') {
                    $latency = 90 + (int) (40 * abs(sin($tick / 4)));
                    if ($chaos && $tick % 11 === 0) {
                        $latency = random_int(1600, 2800);
                        $this->note('shop latency spike');
                    }
                }

                if ($check->type === CheckType::Icmp) {
                    $latency = random_int(8, 35);
                    if ($chaos && $tick % 19 === 0) {
                        $ok = false;
                        $latency = null;
                        $error = 'ICMP timeout (demo)';
                    }
                }

                if ($check->type === CheckType::Tcp && $chaos && $tick % 17 === 0 && $check->name === 'Demo SMTP') {
                    $ok = false;
                    $latency = null;
                    $error = 'Connection refused (demo chaos)';
                }

                $recorder->record(
                    $check,
                    $ok,
                    latencyMs: $latency,
                    error: $error,
                    metadata: ['source' => 'demo-simulate'],
                );
            });
    }

    private function tickChaos(CheckResultRecorder $recorder, int $tick): void
    {
        $targets = Check::query()
            ->whereIn('type', [CheckType::Http, CheckType::Tcp])
            ->whereJsonContains('tags', 'demo')
            ->whereNotIn('name', ['Demo flaky.acme.test'])
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        if ($tick % 3 !== 0) {
            return;
        }

        $check = $targets->random();
        $state = $check->state;
        $currentlyDown = $state?->status === CheckStatus::Down;
        $ok = $currentlyDown || random_int(0, 2) > 0;

        $recorder->record(
            $check,
            $ok,
            latencyMs: $ok ? random_int(50, 450) : null,
            error: $ok ? null : 'Simulated outage (demo chaos)',
            metadata: ['source' => 'demo-chaos'],
        );

        $this->note("chaos {$check->name} → ".($ok ? 'up' : 'down'));
    }

    private function note(string $message): void
    {
        $this->tickNotes[] = $message;
    }
}
