<?php

namespace App\Modules\Setup\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class Installer
{
    public function __construct(private EnvWriter $env, private InstallState $state) {}

    /**
     * Laravel connection config for the wizard's database answers.
     *
     * @param  array<string, mixed>  $db
     * @return array<string, mixed>
     */
    public function connectionConfig(array $db): array
    {
        return match ($db['driver']) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $db['database'] ?: database_path('database.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'mysql', 'pgsql' => [
                'driver' => $db['driver'],
                'host' => $db['host'],
                'port' => $db['port'] ?: ($db['driver'] === 'mysql' ? 3306 : 5432),
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'] ?? '',
                'charset' => $db['driver'] === 'mysql' ? 'utf8mb4' : 'utf8',
                'collation' => $db['driver'] === 'mysql' ? 'utf8mb4_unicode_ci' : null,
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ],
            default => throw new InvalidArgumentException('Unsupported database driver.'),
        };
    }

    /**
     * Try to connect; returns an error message or null when OK.
     *
     * @param  array<string, mixed>  $db
     */
    public function testConnection(array $db): ?string
    {
        try {
            $config = $this->connectionConfig($db);

            if ($config['driver'] === 'sqlite' && $config['database'] !== ':memory:') {
                File::ensureDirectoryExists(dirname($config['database']));
                File::exists($config['database']) || File::put($config['database'], '');
            }

            config(['database.connections.phare_setup' => $config]);
            DB::purge('phare_setup');
            DB::connection('phare_setup')->getPdo();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Run the whole first setup: DB, migrations, admin, .env, marker.
     *
     * @param  array<string, mixed>  $data  validated wizard payload
     */
    /**
     * Wizard fields prefilled from the active Laravel DB connection (.env / Forge).
     *
     * @return array{driver: string, database: string, host: string, port: string, username: string, password: string}
     */
    public function dbFromApplicationConfig(): array
    {
        $driver = (string) config('database.default');
        $connection = config("database.connections.{$driver}");

        if (! is_array($connection)) {
            return [
                'driver' => 'sqlite',
                'database' => database_path('database.sqlite'),
                'host' => '',
                'port' => '',
                'username' => '',
                'password' => '',
            ];
        }

        return [
            'driver' => $driver,
            'database' => (string) ($connection['database'] ?? ''),
            'host' => (string) ($connection['host'] ?? ''),
            'port' => isset($connection['port']) && $connection['port'] !== null ? (string) $connection['port'] : '',
            'username' => (string) ($connection['username'] ?? ''),
            'password' => '',
        ];
    }

    /**
     * @return array{
     *     connected: bool,
     *     migrated: bool,
     *     use_configured: bool,
     *     summary: string|null,
     *     prefill: array{driver: string, database: string, host: string, port: string, username: string, password: string}
     * }
     */
    public function configuredDatabaseStatus(): array
    {
        $prefill = $this->dbFromApplicationConfig();
        $status = [
            'connected' => false,
            'migrated' => false,
            'use_configured' => false,
            'summary' => null,
            'prefill' => $prefill,
        ];

        try {
            $connection = DB::connection();
            $connection->getPdo();
            $status['connected'] = true;

            $migrated = Schema::connection($connection->getName())->hasTable('migrations')
                && $connection->table('migrations')->exists()
                && Schema::connection($connection->getName())->hasTable('users');

            $status['migrated'] = $migrated;
            $status['use_configured'] = $status['connected'] && $migrated && ! app()->environment('testing');
            $status['summary'] = $this->connectionSummary($prefill, $status['migrated']);
        } catch (Throwable) {
            // Wizard stays on manual DB configuration.
        }

        return $status;
    }

    /**
     * @param  array{driver: string, database: string, host: string, port: string, username: string, password: string}  $prefill
     */
    private function connectionSummary(array $prefill, bool $migrated): string
    {
        $label = match ($prefill['driver']) {
            'sqlite' => $prefill['database'],
            'mysql', 'pgsql' => $prefill['host'].' / '.$prefill['database'],
            default => $prefill['driver'],
        };

        return $migrated
            ? __(':engine — schema ready', ['engine' => strtoupper($prefill['driver']).' · '.$label])
            : __(':engine — connected', ['engine' => strtoupper($prefill['driver']).' · '.$label]);
    }

    public function install(array $data): User
    {
        $useConfigured = (bool) ($data['use_configured_database'] ?? false);
        $config = $useConfigured
            ? config('database.connections.'.config('database.default'))
            : $this->connectionConfig($data['db']);

        if (! $useConfigured) {
            config(['database.connections.phare_setup' => $config, 'database.default' => 'phare_setup']);
            DB::purge('phare_setup');
        }

        Artisan::call('migrate', ['--force' => true]);

        $user = User::query()->where('email', $data['admin']['email'])->first() ?? new User;
        $user->forceFill([
            'name' => $data['admin']['name'],
            'email' => $data['admin']['email'],
            'password' => $data['admin']['password'],
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->env->write($this->envValues($data, $config, $useConfigured));
        $this->state->markInstalled();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     * @return array<string, string|null>
     */
    private function envValues(array $data, array $config, bool $keepDatabaseEnv = false): array
    {
        $values = [
            'APP_NAME' => $data['instance']['name'],
            'APP_URL' => rtrim($data['instance']['url'], '/'),
            'APP_LOCALE' => $data['instance']['locale'],
            'APP_TIMEZONE' => $data['instance']['timezone'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ];

        if (! $keepDatabaseEnv) {
            $values['DB_CONNECTION'] = $config['driver'];
        }

        if (! config('app.key')) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if (! config('vault.master_key')) {
            $values['VAULT_MASTER_KEY'] = base64_encode(random_bytes(32));
        }

        if (! $keepDatabaseEnv) {
            if ($config['driver'] === 'sqlite') {
                $values['DB_DATABASE'] = $config['database'];
            } else {
                $values += [
                    'DB_HOST' => $config['host'],
                    'DB_PORT' => (string) $config['port'],
                    'DB_DATABASE' => $config['database'],
                    'DB_USERNAME' => $config['username'],
                    'DB_PASSWORD' => $config['password'],
                ];
            }
        }

        $values += ['SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database'];

        $mail = $data['mail'] ?? [];
        if (! empty($mail['host'])) {
            $values += [
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => $mail['host'],
                'MAIL_PORT' => (string) ($mail['port'] ?? 587),
                'MAIL_USERNAME' => $mail['username'] ?? '',
                'MAIL_PASSWORD' => $mail['password'] ?? '',
                'MAIL_FROM_ADDRESS' => $mail['from'] ?? 'phare@'.Str::after($values['APP_URL'], '://'),
                'MAIL_FROM_NAME' => $data['instance']['name'],
            ];
        }

        return $values;
    }
}
