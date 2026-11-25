<?php

namespace Nocline\Laravel\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'nocline:heartbeat:install {token? : The token of your heartbeat (last part of its ping address)}';

    protected $description = 'Print the lines to add to your project to report a scheduled task to Nocline';

    public function handle(): int
    {
        $token = $this->argument('token');
        $token = is_string($token) && $token !== '' ? $token : 'YOUR_HEARTBEAT_TOKEN';

        $this->line('1. In your .env file:');
        $this->newLine();
        $this->line('   NOCLINE_URL=https://your-nocline-hub.example.com');
        $this->line("   NOCLINE_HEARTBEAT_TOKEN={$token}");
        $this->newLine();
        $this->line('2. In routes/console.php (or app/Console/Kernel.php):');
        $this->newLine();
        $this->line("   Schedule::command('backup:run')->daily()");
        $this->line('       ->pingNoclineOnSuccess()');
        $this->line('       ->pingNoclineOnFailure();');
        $this->newLine();
        $this->line('3. Or, from any job or controller:');
        $this->newLine();
        $this->line('   \Nocline\Laravel\Facades\Nocline::heartbeat();');
        $this->newLine();
        $this->components->info('Nocline warns you when the task stops reporting.');

        return self::SUCCESS;
    }
}
