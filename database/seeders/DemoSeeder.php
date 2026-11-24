<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Coffre\Services\VaultCrypto;
use App\Modules\Groups\Enums\GroupLayout;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\Probes\Models\Probe;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\StatusPages\Models\StatusPage;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Full realistic dataset for manual UI testing. Idempotent by fixed names.
 * Writes plaintext agent/heartbeat tokens to storage/app/phare-demo.json for phare:demo:simulate.
 */
class DemoSeeder extends Seeder
{
    public const MANIFEST = 'phare-demo.json';

    /** Fast polling for local UI demos (bypasses form validation minimums). */
    public const INTERVAL_SEC = 5;

    /** Heartbeat grace so missed pings show as down within ~15 s at INTERVAL_SEC. */
    public const HEARTBEAT_GRACE_SEC = 10;

    /** @var array<string, mixed> */
    private array $manifest = [
        'hosts' => [],
        'probes' => [],
        'heartbeats' => [],
    ];

    public function run(): void
    {
        $admin = $this->users();
        $groups = $this->groups();
        $hosts = $this->hosts($groups);
        $probes = $this->probes();
        $checks = $this->checks($groups, $hosts);
        $this->incidentsAndStates($checks);
        $this->renewals($groups, $checks);
        $this->alerts();
        $this->maintenance($groups);
        $this->statusAndWallboard($groups);
        $this->vault($groups, $hosts['web-1'] ?? null);

        $this->manifest['login'] = [
            'admin_email' => $admin->email,
            'demo_email' => 'demo@phare.test',
            'demo_password' => 'password',
            'note' => 'Use demo@phare.test / password, or your existing admin account.',
        ];

        File::put(
            storage_path('app/'.self::MANIFEST),
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->command?->info('Demo data ready. Tokens: storage/app/'.self::MANIFEST);
        $this->command?->info('Sign in: demo@phare.test / password (or your existing admin).');
        $this->command?->info('Live traffic: php artisan phare:demo:simulate --chaos --interval=5');
    }

    private function users(): User
    {
        $demo = User::query()->firstOrCreate(
            ['email' => 'demo@phare.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ],
        );

        if ($demo->role !== UserRole::Admin) {
            $demo->forceFill(['role' => UserRole::Admin])->save();
        }

        User::query()->firstOrCreate(
            ['email' => 'tech@phare.test'],
            [
                'name' => 'Camille Tech',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Tech,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'viewer@phare.test'],
            [
                'name' => 'Alex Viewer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Viewer,
            ],
        );

        return User::query()->where('role', UserRole::Admin)->orderBy('id')->first() ?? $demo;
    }

    /**
     * @return array{root: Group, acme: Group, sites: Group, infra: Group, pegase: Group}
     */
    private function groups(): array
    {
        $root = Group::query()->firstOrCreate(
            ['name' => 'Demo'],
            ['color' => '#0d9488', 'layout' => GroupLayout::List, 'position' => 0],
        );

        $acme = Group::query()->firstOrCreate(
            ['name' => 'Acme Corp', 'parent_id' => $root->id],
            ['color' => '#0284c7', 'layout' => GroupLayout::Grid, 'position' => 0],
        );

        $sites = Group::query()->firstOrCreate(
            ['name' => 'Sites web', 'parent_id' => $acme->id],
            ['color' => '#059669', 'layout' => GroupLayout::List, 'position' => 0],
        );

        $infra = Group::query()->firstOrCreate(
            ['name' => 'Infrastructure', 'parent_id' => $acme->id],
            ['color' => '#d97706', 'layout' => GroupLayout::Compact, 'position' => 1],
        );

        $pegase = Group::query()->firstOrCreate(
            ['name' => 'Pégase', 'parent_id' => $root->id],
            ['color' => '#7c3aed', 'layout' => GroupLayout::List, 'position' => 1],
        );

        return compact('root', 'acme', 'sites', 'infra', 'pegase');
    }

    /**
     * @param  array{infra: Group, sites: Group}  $groups
     * @return array<string, Host>
     */
    private function hosts(array $groups): array
    {
        $defs = [
            'web-1' => ['name' => 'Demo web-1', 'group_id' => $groups['infra']->id, 'os' => 'linux', 'token' => 'pha_demo_web1_'.str_repeat('a', 32)],
            'db-1' => ['name' => 'Demo db-1', 'group_id' => $groups['infra']->id, 'os' => 'linux', 'token' => 'pha_demo_db1_'.str_repeat('b', 32)],
            'offline' => ['name' => 'Demo offline-box', 'group_id' => $groups['infra']->id, 'os' => 'linux', 'token' => null],
        ];

        $hosts = [];

        foreach ($defs as $key => $def) {
            $host = Host::query()->firstOrCreate(
                ['name' => $def['name']],
                ['group_id' => $def['group_id'], 'tags' => ['demo']],
            );

            $host->forceFill([
                'group_id' => $def['group_id'],
                'tags' => ['demo'],
                'os' => $def['os'],
                'agent_version' => $def['token'] ? '0.1.0-demo' : null,
                'enrolled_at' => $def['token'] ? now()->subDays(3) : null,
                'api_token_hash' => $def['token'] ? Host::hashToken($def['token']) : null,
                'last_seen_at' => $def['token'] ? now()->subSeconds(30) : now()->subHours(6),
            ])->save();

            if ($def['token']) {
                $this->manifest['hosts'][$key] = $def['token'];
            }

            $hosts[$key] = $host->fresh();
        }

        return $hosts;
    }

    /**
     * @return array<string, Probe>
     */
    private function probes(): array
    {
        $defs = [
            'paris' => ['name' => 'Demo probe Paris', 'role' => 'primary', 'token' => 'prp_demo_paris_'.str_repeat('c', 32)],
            'external' => ['name' => 'Demo probe External', 'role' => 'external', 'token' => 'prp_demo_ext_'.str_repeat('d', 32)],
        ];

        $probes = [];

        foreach ($defs as $key => $def) {
            $probe = Probe::query()->firstOrCreate(
                ['name' => $def['name']],
                ['role' => $def['role'], 'enabled' => true],
            );

            $probe->forceFill([
                'role' => $def['role'],
                'enabled' => true,
                'capabilities' => ['http', 'tcp', 'icmp', 'dns'],
                'agent_version' => '0.1.0-demo',
                'enrolled_at' => now()->subDays(2),
                'api_token_hash' => Probe::hashToken($def['token']),
                'last_seen_at' => now()->subSeconds(20),
            ])->save();

            $this->manifest['probes'][$key] = $def['token'];
            $probes[$key] = $probe->fresh();
        }

        return $probes;
    }

    /**
     * @param  array{sites: Group, infra: Group, pegase: Group}  $groups
     * @param  array<string, Host>  $hosts
     * @return array<string, Check>
     */
    private function checks(array $groups, array $hosts): array
    {
        $defs = [
            'shop' => [
                'name' => 'Demo shop.acme.test',
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://example.com',
                'category' => CheckCategory::Website,
                'tags' => ['demo', 'laravel'],
            ],
            'api' => [
                'name' => 'Demo api.acme.test',
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://httpbin.org/status/200',
                'category' => CheckCategory::Website,
                'tags' => ['demo'],
            ],
            'flaky' => [
                'name' => 'Demo flaky.acme.test',
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://httpbin.org/status/500',
                'category' => CheckCategory::Website,
                'tags' => ['demo'],
                'failure_threshold' => 1,
            ],
            'smtp' => [
                'name' => 'Demo SMTP',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Tcp,
                'target' => 'smtp.example.com:587',
                'category' => CheckCategory::Mail,
                'tags' => ['demo'],
            ],
            'ping' => [
                'name' => 'Demo ping gateway',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Icmp,
                'target' => '1.1.1.1',
                'category' => CheckCategory::Network,
                'execution' => CheckExecution::Probe,
                'tags' => ['demo'],
            ],
            'agent-web' => [
                'name' => 'Demo agent web-1',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Agent,
                'target' => '',
                'host_id' => $hosts['web-1']->id,
                'category' => CheckCategory::Server,
                'tags' => ['demo'],
            ],
            'agent-db' => [
                'name' => 'Demo agent db-1',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Agent,
                'target' => '',
                'host_id' => $hosts['db-1']->id,
                'category' => CheckCategory::Server,
                'tags' => ['demo'],
            ],
            'backup' => [
                'name' => 'Demo nightly backup',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Heartbeat,
                'target' => '',
                'interval_sec' => self::INTERVAL_SEC,
                'config' => ['grace_sec' => self::HEARTBEAT_GRACE_SEC],
                'category' => CheckCategory::Other,
                'tags' => ['demo'],
                'heartbeat_key' => 'backup',
            ],
            'cron' => [
                'name' => 'Demo billing cron',
                'group_id' => $groups['pegase']->id,
                'type' => CheckType::Heartbeat,
                'target' => '',
                'interval_sec' => self::INTERVAL_SEC,
                'config' => ['grace_sec' => self::HEARTBEAT_GRACE_SEC],
                'category' => CheckCategory::Other,
                'tags' => ['demo'],
                'heartbeat_key' => 'cron',
            ],
            'pegase-site' => [
                'name' => 'Demo pegase.io',
                'group_id' => $groups['pegase']->id,
                'type' => CheckType::Http,
                'target' => 'https://example.org',
                'category' => CheckCategory::Website,
                'tags' => ['demo'],
            ],
        ];

        $checks = [];

        foreach ($defs as $key => $def) {
            $check = Check::query()->firstOrCreate(
                ['name' => $def['name']],
                [
                    'group_id' => $def['group_id'],
                    'host_id' => $def['host_id'] ?? null,
                    'type' => $def['type'],
                    'target' => $def['target'],
                    'interval_sec' => $def['interval_sec'] ?? self::INTERVAL_SEC,
                    'timeout_sec' => max(5, min(10, self::INTERVAL_SEC * 2)),
                    'failure_threshold' => $def['failure_threshold'] ?? 2,
                    'category' => $def['category'],
                    'tags' => $def['tags'],
                    'enabled' => true,
                    'execution' => $def['execution'] ?? CheckExecution::Hub,
                    'quorum' => 'any',
                    'config' => $def['config'] ?? [],
                ],
            );

            $check->fill([
                'group_id' => $def['group_id'],
                'host_id' => $def['host_id'] ?? null,
                'type' => $def['type'],
                'target' => $def['target'],
                'interval_sec' => $def['interval_sec'] ?? self::INTERVAL_SEC,
                'failure_threshold' => $def['failure_threshold'] ?? 2,
                'category' => $def['category'],
                'tags' => $def['tags'],
                'config' => $def['config'] ?? $check->config,
                'enabled' => true,
                'execution' => $def['execution'] ?? CheckExecution::Hub,
            ])->save();

            if (isset($def['heartbeat_key']) && $check->heartbeat_token) {
                $this->manifest['heartbeats'][$def['heartbeat_key']] = $check->heartbeat_token;
            }

            $checks[$key] = $check->fresh();
        }

        return $checks;
    }

    /**
     * @param  array<string, Check>  $checks
     */
    private function incidentsAndStates(array $checks): void
    {
        $recorder = app(CheckResultRecorder::class);

        foreach (['shop', 'api', 'agent-web', 'agent-db', 'pegase-site', 'smtp', 'ping', 'backup', 'cron'] as $key) {
            if (isset($checks[$key])) {
                $recorder->record($checks[$key], true, latencyMs: random_int(40, 180), metadata: ['source' => 'demo-seed']);
            }
        }

        if (isset($checks['flaky'])) {
            $recorder->record($checks['flaky'], false, error: 'HTTP 500 from origin', metadata: ['source' => 'demo-seed']);
        }
    }

    /**
     * @param  array{sites: Group, pegase: Group}  $groups
     * @param  array<string, Check>  $checks
     */
    private function renewals(array $groups, array $checks): void
    {
        foreach ([
            [
                'label' => 'Demo acme.test domain',
                'kind' => 'domain',
                'group_id' => $groups['sites']->id,
                'check_id' => $checks['shop']->id ?? null,
                'expires_on' => now()->addDays(18)->toDateString(),
                'notes' => 'Seeded demo renewal',
            ],
            [
                'label' => 'Demo shop TLS',
                'kind' => 'ssl',
                'group_id' => $groups['sites']->id,
                'check_id' => $checks['shop']->id ?? null,
                'expires_on' => now()->addDays(45)->toDateString(),
            ],
            [
                'label' => 'Demo hosting contract',
                'kind' => 'contract',
                'group_id' => $groups['pegase']->id,
                'expires_on' => now()->addDays(5)->toDateString(),
                'notes' => 'Renew soon',
            ],
        ] as $row) {
            Renewal::query()->updateOrCreate(
                ['label' => $row['label']],
                [
                    'kind' => $row['kind'],
                    'group_id' => $row['group_id'],
                    'check_id' => $row['check_id'] ?? null,
                    'source' => 'manual',
                    'expires_on' => $row['expires_on'],
                    'remind_days' => [30, 14, 7, 1],
                    'notes' => $row['notes'] ?? null,
                    'last_checked_at' => now(),
                ],
            );
        }
    }

    private function alerts(): void
    {
        NotificationChannel::query()->firstOrCreate(
            ['name' => 'Demo webhook'],
            [
                'type' => 'webhook',
                'enabled' => true,
                'config' => ['url' => 'https://httpbin.org/post'],
            ],
        );
    }

    /**
     * @param  array{infra: Group}  $groups
     */
    private function maintenance(array $groups): void
    {
        MaintenanceWindow::query()->firstOrCreate(
            ['name' => 'Demo weekly patch window'],
            [
                'group_id' => $groups['infra']->id,
                'starts_at' => now()->subMinutes(10),
                'ends_at' => now()->addHour(),
                'recurrence' => MaintenanceRecurrence::None,
            ],
        );
    }

    /**
     * @param  array{acme: Group}  $groups
     */
    private function statusAndWallboard(array $groups): void
    {
        StatusPage::query()->updateOrCreate(
            ['slug' => 'demo-acme'],
            [
                'title' => 'Acme status',
                'description' => 'Public demo status page for Acme Corp.',
                'group_id' => $groups['acme']->id,
                'accent_color' => '#0d9488',
                'show_incidents' => true,
                'enabled' => true,
            ],
        );

        $existing = Wallboard::query()->where('name', 'Demo NOC')->first();

        if ($existing === null) {
            $created = Wallboard::createWithToken([
                'name' => 'Demo NOC',
                'group_id' => $groups['acme']->id,
            ]);
            $this->manifest['wallboard'] = $created['token'];
        } else {
            $token = 'wb_demo_'.Str::repeat('e', 40);
            $existing->forceFill([
                'group_id' => $groups['acme']->id,
                'token_hash' => Wallboard::hashToken($token),
            ])->save();
            $this->manifest['wallboard'] = $token;
        }
    }

    /**
     * @param  array{infra: Group}  $groups
     */
    private function vault(array $groups, ?Host $host): void
    {
        $crypto = app(VaultCrypto::class);

        if (! $crypto->isConfigured()) {
            $this->command?->warn('VAULT_MASTER_KEY missing — skipping vault demo entries.');

            return;
        }

        $entry = VaultEntry::query()->firstOrNew(['label' => 'Demo deploy key']);
        $entry->group_id = $groups['infra']->id;
        $entry->host_id = $host?->id;
        $entry->url = 'https://deploy.example.com';
        $entry->seal([
            'username' => 'deploy',
            'secret' => 'demo-secret-do-not-use-in-prod',
            'notes' => 'Seeded vault secret',
        ], $crypto);
        $entry->save();
    }
}
