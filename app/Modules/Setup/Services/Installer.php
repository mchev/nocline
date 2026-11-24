<?php

namespace App\Modules\Setup\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
    public function install(array $data): User
    {
        $config = $this->connectionConfig($data['db']);

        config(['database.connections.phare_setup' => $config, 'database.default' => 'phare_setup']);
        DB::purge('phare_setup');

        Artisan::call('migrate', ['--force' => true]);

        $user = User::query()->where('email', $data['admin']['email'])->first() ?? new User;
        $user->forceFill([
            'name' => $data['admin']['name'],
            'email' => $data['admin']['email'],
            'password' => $data['admin']['password'],
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->env->write($this->envValues($data, $config));
        $this->state->markInstalled();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     * @return array<string, string|null>
     */
    private function envValues(array $data, array $config): array
    {
        $values = [
            'APP_NAME' => $data['instance']['name'],
            'APP_URL' => rtrim($data['instance']['url'], '/'),
            'APP_LOCALE' => $data['instance']['locale'],
            'APP_TIMEZONE' => $data['instance']['timezone'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => $config['driver'],
        ];

        if (! config('app.key')) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if (! config('vault.master_key')) {
            $values['VAULT_MASTER_KEY'] = base64_encode(random_bytes(32));
        }

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
