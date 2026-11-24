<?php

namespace Phare\Laravel\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'phare:heartbeat:install {token? : The token of your heartbeat (last part of its ping address)}';

    protected $description = 'Print the lines to add to your project to report a scheduled task to Phare';

    public function handle(): int
    {
        $token = $this->argument('token');
        $token = is_string($token) && $token !== '' ? $token : 'YOUR_HEARTBEAT_TOKEN';

        $this->line('1. In your .env file:');
        $this->newLine();
        $this->line('   PHARE_URL=https://your-phare-hub.example.com');
        $this->line("   PHARE_HEARTBEAT_TOKEN={$token}");
        $this->newLine();
        $this->line('2. In routes/console.php (or app/Console/Kernel.php):');
        $this->newLine();
        $this->line("   Schedule::command('backup:run')->daily()");
        $this->line('       ->pingPhareOnSuccess()');
        $this->line('       ->pingPhareOnFailure();');
        $this->newLine();
        $this->line('3. Or, from any job or controller:');
        $this->newLine();
        $this->line('   \Phare\Laravel\Facades\Phare::heartbeat();');
        $this->newLine();
        $this->components->info('Phare warns you when the task stops reporting.');

        return self::SUCCESS;
    }
}
