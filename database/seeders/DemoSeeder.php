<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Coffre\Services\VaultCrypto;
use App\Modules\Groups\Enums\GroupLayout;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Services\EndOfLifeCatalog;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Probes\Models\Probe;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\StatusPages\Models\StatusPage;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Full realistic dataset for manual UI testing. Idempotent by fixed names.
 * Writes plaintext agent/heartbeat tokens to storage/app/nocline-demo.json for nocline:demo:simulate.
 */
class DemoSeeder extends Seeder
{
    public const MANIFEST = 'nocline-demo.json';

    public const CHECK_SHOP = 'shop.acme.com';

    public const CHECK_SHOP_HEALTH = 'shop.acme.com — health';

    public const CHECK_FLAKY = 'flaky.acme.com';

    public const CHECK_SMTP = 'mail.acme.com';

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
        $this->seedStackEolCatalog();
        $hosts = $this->hosts($groups);
        $probes = $this->probes();
        $checks = $this->checks($groups, $hosts);
        $this->seedMetricHistory($hosts);
        $this->incidentsAndStates($checks);
        $this->renewals($groups, $checks);
        $this->alerts();
        $this->maintenance($groups);
        $this->statusAndWallboard($groups);
        $this->vault($groups, $hosts['web-1'] ?? null);

        $this->manifest['login'] = [
            'admin_email' => $admin->email,
            'demo_email' => 'demo@nocline.test',
            'demo_password' => 'password',
            'note' => 'Use demo@nocline.test / password, or your existing admin account.',
        ];

        $this->manifest['screenshot'] = [
            'status_slug' => 'demo-acme',
            'host_db_id' => $hosts['db-1']->id ?? null,
            'host_web_id' => $hosts['web-1']->id ?? null,
            'host_offline_id' => $hosts['offline']->id ?? null,
            'check_shop_id' => $checks['shop']->id ?? null,
            'check_blog_id' => $checks['blog']->id ?? null,
            'check_flaky_id' => $checks['flaky']->id ?? null,
            'heartbeat_backup_id' => $checks['backup']->id ?? null,
        ];

        File::put(
            storage_path('app/'.self::MANIFEST),
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->command?->info('Demo data ready. Tokens: storage/app/'.self::MANIFEST);
        $this->command?->info('Sign in: demo@nocline.test / password (or your existing admin).');
        $this->command?->info('Live traffic: php artisan nocline:demo:simulate --chaos --interval=5');
    }

    private function users(): User
    {
        $demo = User::query()->firstOrCreate(
            ['email' => 'demo@nocline.test'],
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
            ['email' => 'tech@nocline.test'],
            [
                'name' => 'Camille Tech',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Tech,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'viewer@nocline.test'],
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
            ['color' => '#365CF5', 'layout' => GroupLayout::List, 'position' => 0],
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
        $agentMeta = [
            'web-1' => [
                'hostname' => 'acme-web-01',
                'public_ipv4' => '91.98.12.44',
                'distro' => 'Ubuntu 24.04 LTS',
                'provider' => 'hetzner',
                'provider_region' => 'fsn1',
                'provider_source' => 'agent',
                'kernel' => '6.8.0-51-generic',
                'stack' => [
                    'php' => '8.5.0',
                    'node' => '22.14.0',
                    'nginx' => '1.31.3',
                    'redis' => '7.4.2',
                ],
            ],
            'db-1' => [
                'hostname' => 'acme-db-01',
                'public_ipv4' => '51.210.88.17',
                'distro' => 'Debian 12',
                'provider' => 'ovh',
                'provider_region' => 'gra',
                'provider_source' => 'agent',
                'kernel' => '6.1.0-23-amd64',
                'stack' => [
                    'php' => '8.1.33',
                    'node' => '18.20.8',
                    'postgresql' => '14.20',
                    'redis' => '7.0.15',
                    'mysql' => '8.0.36',
                ],
            ],
            'offline' => [
                'hostname' => 'legacy-staging',
                'public_ipv4' => '188.165.42.9',
                'distro' => 'Ubuntu 22.04 LTS',
                'provider' => 'scaleway',
                'provider_region' => 'fr-par-1',
                'provider_source' => 'agent',
            ],
        ];

        $defs = [
            'web-1' => ['name' => 'acme-web-01', 'group_id' => $groups['infra']->id, 'os' => 'Ubuntu 24.04 LTS', 'token' => 'pha_demo_web1_'.str_repeat('a', 32)],
            'db-1' => ['name' => 'acme-db-01', 'group_id' => $groups['infra']->id, 'os' => 'Debian 12', 'token' => 'pha_demo_db1_'.str_repeat('b', 32)],
            'offline' => ['name' => 'legacy-staging', 'group_id' => $groups['infra']->id, 'os' => 'Ubuntu 22.04 LTS', 'token' => null],
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
                'color' => $key === 'db-1' ? '#d97706' : ($key === 'web-1' ? '#0284c7' : '#94a3b8'),
                'os' => $def['os'],
                'agent_version' => $def['token'] ? '0.2.1-demo' : null,
                'enrolled_at' => $def['token'] ? now()->subDays(3) : null,
                'api_token_hash' => $def['token'] ? Host::hashToken($def['token']) : null,
                'last_seen_at' => $def['token'] ? now()->subSeconds(30) : now()->subHours(6),
                'agent_meta' => $agentMeta[$key],
                'agent_meta_at' => now(),
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
            'paris' => ['name' => 'Probe · Paris (primary)', 'role' => 'primary', 'token' => 'prp_demo_paris_'.str_repeat('c', 32)],
            'external' => ['name' => 'Probe · Frankfurt (external)', 'role' => 'external', 'token' => 'prp_demo_ext_'.str_repeat('d', 32)],
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
                'agent_version' => '0.2.1-demo',
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
                'name' => self::CHECK_SHOP,
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://example.com',
                'category' => CheckCategory::Website,
                'tags' => ['demo', SiteFramework::Laravel->value],
            ],
            'blog' => [
                'name' => 'blog.acme.com',
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://wordpress.org',
                'category' => CheckCategory::Website,
                'tags' => ['demo', SiteFramework::WordPress->value],
                'config' => [
                    'wordpress' => [
                        'version' => '6.6.2',
                        'detected_at' => now()->subDay()->toIso8601String(),
                    ],
                ],
            ],
            'api' => [
                'name' => 'api.acme.com',
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://httpbin.org/status/200',
                'category' => CheckCategory::Website,
                'tags' => ['demo', SiteFramework::Laravel->value],
            ],
            'flaky' => [
                'name' => self::CHECK_FLAKY,
                'group_id' => $groups['sites']->id,
                'type' => CheckType::Http,
                'target' => 'https://httpbin.org/status/500',
                'category' => CheckCategory::Website,
                'tags' => ['demo'],
                'failure_threshold' => 1,
            ],
            'smtp' => [
                'name' => self::CHECK_SMTP,
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Tcp,
                'target' => 'smtp.example.com:587',
                'category' => CheckCategory::Mail,
                'tags' => ['demo'],
            ],
            'ping' => [
                'name' => 'Gateway ICMP',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Icmp,
                'target' => '1.1.1.1',
                'category' => CheckCategory::Network,
                'execution' => CheckExecution::Probe,
                'tags' => ['demo'],
            ],
            'ovh-s3' => [
                'name' => 'OVH Object Storage (gra)',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Tcp,
                'target' => 's3.gra.io.cloud.ovh.net:443',
                'category' => CheckCategory::Other,
                'tags' => ['demo', 'object-storage', 'ovh'],
            ],
            'hetzner-bucket' => [
                'name' => 'acme-media bucket',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Http,
                'target' => 'https://httpbin.org/status/200',
                'category' => CheckCategory::Other,
                'tags' => ['demo', 'object-storage', 'hetzner', 's3-bucket'],
            ],
            'agent-web' => [
                'name' => 'acme-web-01 (agent)',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Agent,
                'target' => '',
                'host_id' => $hosts['web-1']->id,
                'category' => CheckCategory::Server,
                'tags' => ['demo'],
            ],
            'agent-db' => [
                'name' => 'acme-db-01 (agent)',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Agent,
                'target' => '',
                'host_id' => $hosts['db-1']->id,
                'category' => CheckCategory::Server,
                'tags' => ['demo'],
            ],
            'backup' => [
                'name' => 'Nightly backup',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Heartbeat,
                'target' => '',
                'interval_sec' => self::INTERVAL_SEC,
                'config' => ['grace_sec' => self::HEARTBEAT_GRACE_SEC],
                'category' => CheckCategory::Other,
                'tags' => ['demo'],
                'heartbeat_key' => 'backup',
            ],
            's3-backup' => [
                'name' => 'Rclone → OVH (daily)',
                'group_id' => $groups['infra']->id,
                'type' => CheckType::Heartbeat,
                'target' => '',
                'interval_sec' => self::INTERVAL_SEC,
                'config' => ['grace_sec' => self::HEARTBEAT_GRACE_SEC],
                'category' => CheckCategory::Other,
                'tags' => ['demo', 'object-storage', 'ovh', 's3-backup'],
                'heartbeat_key' => 's3-backup',
            ],
            'cron' => [
                'name' => 'Billing cron',
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
                'name' => 'pegase.io',
                'group_id' => $groups['pegase']->id,
                'type' => CheckType::Http,
                'target' => 'https://example.org',
                'category' => CheckCategory::Website,
                'tags' => ['demo', SiteFramework::Laravel->value],
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

            $check->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

            if (isset($def['heartbeat_key']) && $check->heartbeat_token) {
                $this->manifest['heartbeats'][$def['heartbeat_key']] = $check->heartbeat_token;
            }

            $checks[$key] = $check->fresh();
        }

        $this->ensureLaravelHealthCompanion($checks['shop'] ?? null);

        return $checks;
    }

    /**
     * @param  array<string, Check>  $checks
     */
    private function incidentsAndStates(array $checks): void
    {
        $recorder = app(CheckResultRecorder::class);

        foreach (['shop', 'blog', 'api', 'agent-web', 'agent-db', 'pegase-site', 'smtp', 'ping', 'backup', 'cron', 'ovh-s3', 'hetzner-bucket', 's3-backup'] as $key) {
            if (isset($checks[$key])) {
                $recorder->record($checks[$key], true, latencyMs: random_int(40, 180), metadata: ['source' => 'demo-seed']);
            }
        }

        $health = Check::query()->where('name', self::CHECK_SHOP_HEALTH)->first();
        if ($health !== null) {
            $recorder->record($health, true, latencyMs: random_int(25, 90), metadata: ['source' => 'demo-seed']);
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
                'label' => 'acme.com domain',
                'kind' => 'domain',
                'group_id' => $groups['sites']->id,
                'check_id' => $checks['shop']->id ?? null,
                'expires_on' => now()->addDays(12)->toDateString(),
                'notes' => 'Registrar auto-renew ON',
            ],
            [
                'label' => 'shop.acme.com TLS',
                'kind' => 'ssl',
                'group_id' => $groups['sites']->id,
                'check_id' => $checks['shop']->id ?? null,
                'expires_on' => now()->addDays(9)->toDateString(),
            ],
            [
                'label' => 'blog.acme.com TLS',
                'kind' => 'ssl',
                'group_id' => $groups['sites']->id,
                'check_id' => $checks['blog']->id ?? null,
                'expires_on' => now()->addDays(38)->toDateString(),
            ],
            [
                'label' => 'Hetzner invoice contract',
                'kind' => 'contract',
                'group_id' => $groups['pegase']->id,
                'expires_on' => now()->addDays(4)->toDateString(),
                'notes' => 'Renew soon',
            ],
        ] as $row) {
            $keys = array_key_exists('check_id', $row)
                ? ['check_id' => $row['check_id'], 'kind' => $row['kind']]
                : ['label' => $row['label']];

            Renewal::query()->updateOrCreate(
                $keys,
                [
                    'label' => $row['label'],
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
                'accent_color' => '#365CF5',
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

    private function seedStackEolCatalog(): void
    {
        Cache::put(EndOfLifeCatalog::CACHE_KEY, [
            'fetched_at' => now()->toIso8601String(),
            'products' => [
                'php' => [
                    'releases' => [
                        [
                            'name' => '8.1',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2025-11-25',
                            'latest' => '8.1.33',
                        ],
                        [
                            'name' => '8.5',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => '2029-12-31',
                            'latest' => '8.5.13',
                        ],
                    ],
                ],
                'nodejs' => [
                    'releases' => [
                        [
                            'name' => '18',
                            'isEol' => true,
                            'isLts' => true,
                            'isMaintained' => false,
                            'eolFrom' => '2025-04-30',
                            'latest' => '18.20.8',
                        ],
                        [
                            'name' => '22',
                            'isEol' => false,
                            'isLts' => true,
                            'isMaintained' => true,
                            'eolFrom' => '2027-04-30',
                            'latest' => '22.14.0',
                        ],
                    ],
                ],
                'postgresql' => [
                    'releases' => [
                        [
                            'name' => '14',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2026-11-12',
                            'latest' => '14.20',
                        ],
                        [
                            'name' => '16',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => null,
                            'latest' => '16.8',
                        ],
                    ],
                ],
                'redis' => [
                    'releases' => [
                        [
                            'name' => '7.0',
                            'isEol' => true,
                            'isLts' => false,
                            'isMaintained' => false,
                            'eolFrom' => '2024-07-29',
                            'latest' => '7.0.15',
                        ],
                        [
                            'name' => '7.4',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => null,
                            'latest' => '7.4.2',
                        ],
                    ],
                ],
                'mysql' => [
                    'releases' => [
                        [
                            'name' => '8.0',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => '2026-04-30',
                            'latest' => '8.0.36',
                        ],
                    ],
                ],
                'nginx' => [
                    'releases' => [
                        [
                            'name' => '1.31',
                            'isEol' => false,
                            'isLts' => false,
                            'isMaintained' => true,
                            'eolFrom' => null,
                            'latest' => '1.31.9',
                        ],
                    ],
                ],
            ],
        ], now()->addDays(7));
    }

    /**
     * @param  array<string, Host>  $hosts
     */
    private function seedMetricHistory(array $hosts): void
    {
        $profiles = [
            'web-1' => ['cpu' => 18, 'mem' => 42, 'disk' => 54],
            'db-1' => ['cpu' => 34, 'mem' => 76, 'disk' => 81],
        ];

        foreach ($profiles as $key => $base) {
            $host = $hosts[$key] ?? null;

            if ($host === null) {
                continue;
            }

            for ($i = 29; $i >= 0; $i--) {
                $bucket = now()->subMinutes($i)->startOfMinute();
                $wave = (int) (8 * sin($i / 4));

                foreach ([
                    'cpu.usage_pct' => max(4, $base['cpu'] + $wave + ($key === 'db-1' ? 6 : 0)),
                    'mem.used_pct' => max(8, $base['mem'] + intdiv($i, 5)),
                    'disk.used_pct' => $base['disk'],
                ] as $metricKey => $avg) {
                    MetricRollup::query()->updateOrCreate(
                        [
                            'host_id' => $host->id,
                            'metric_key' => $metricKey,
                            'bucket_size' => MetricRollup::BUCKET_1M,
                            'bucket_start' => $bucket,
                        ],
                        [
                            'min' => $avg - 2,
                            'max' => $avg + 3,
                            'avg' => $avg,
                            'p95' => $avg + 2,
                            'sample_count' => 2,
                        ],
                    );
                }
            }
        }
    }

    private function ensureLaravelHealthCompanion(?Check $shop): void
    {
        if ($shop === null) {
            return;
        }

        $parts = parse_url($shop->target);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        $health = Check::query()->firstOrCreate(
            ['name' => self::CHECK_SHOP_HEALTH],
            [
                'type' => CheckType::Http,
                'category' => CheckCategory::Website,
                'target' => $origin.'/up',
                'group_id' => $shop->group_id,
                'tags' => [SiteFramework::Laravel->value, 'demo'],
                'interval_sec' => self::INTERVAL_SEC,
                'timeout_sec' => 10,
                'failure_threshold' => 2,
                'config' => ['companion_of' => $shop->id],
                'enabled' => true,
            ],
        );

        $config = is_array($shop->config) ? $shop->config : [];
        $shop->forceFill([
            'config' => [...$config, 'health_check_id' => $health->id],
        ])->save();
    }
}
