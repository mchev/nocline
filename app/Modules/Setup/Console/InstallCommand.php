<?php

namespace App\Modules\Setup\Console;

use App\Modules\Setup\Services\Installer;
use App\Modules\Setup\Services\InstallState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:install
    {--db=sqlite : sqlite|mysql|pgsql}
    {--db-host=127.0.0.1} {--db-port=} {--db-name=} {--db-user=} {--db-password=}
    {--admin-name=} {--admin-email=} {--admin-password=}
    {--name=Phare} {--url=http://localhost:8000} {--locale=fr} {--timezone=Europe/Paris}')]
#[Description('Non-interactive first setup (Docker / Forge): database, migrations, first admin')]
class InstallCommand extends Command
{
    public function handle(Installer $installer, InstallState $state): int
    {
        if ($state->isInstalled()) {
            $this->components->error('Already installed.');

            return self::FAILURE;
        }

        $data = [
            'db' => [
                'driver' => $this->option('db'),
                'host' => $this->option('db-host'),
                'port' => $this->option('db-port'),
                'database' => $this->option('db-name') ?: ($this->option('db') === 'sqlite' ? '' : 'phare'),
                'username' => $this->option('db-user'),
                'password' => $this->option('db-password'),
            ],
            'admin' => [
                'name' => $this->option('admin-name') ?: $this->ask('Admin name'),
                'email' => $this->option('admin-email') ?: $this->ask('Admin email'),
                'password' => $this->option('admin-password') ?: $this->secret('Admin password (12+ chars)'),
            ],
            'instance' => [
                'name' => $this->option('name'),
                'url' => $this->option('url'),
                'locale' => $this->option('locale'),
                'timezone' => $this->option('timezone'),
            ],
        ];

        if (strlen($data['admin']['password']) < 12) {
            $this->components->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        if ($error = $installer->testConnection($data['db'])) {
            $this->components->error("Database connection failed: {$error}");

            return self::FAILURE;
        }

        $installer->install($data);
        $this->components->info('Phare installed. Log in at '.$data['instance']['url']);

        return self::SUCCESS;
    }
}
