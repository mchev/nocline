<?php

namespace Phare\Laravel\Console;

use Illuminate\Console\Command;
use Phare\Laravel\PhareClient;

class PingCommand extends Command
{
    protected $signature = 'phare:ping
        {token? : Heartbeat token or configured name (defaults to PHARE_HEARTBEAT_TOKEN)}
        {--fail : Report a failure instead of a success}
        {--message= : Short text shown in Phare when reporting a failure}';

    protected $description = 'Send a heartbeat ping to Phare';

    public function handle(PhareClient $phare): int
    {
        $token = $this->argument('token');
        $token = is_string($token) ? $token : null;
        $message = $this->option('message');
        $message = is_string($message) ? $message : null;

        $delivered = $this->option('fail') ? $phare->fail($token, $message) : $phare->heartbeat($token, $message);

        if (! $delivered) {
            $this->components->warn('The ping was not delivered (check PHARE_URL and the token).');

            return self::FAILURE;
        }

        $this->components->info('Ping sent.');

        return self::SUCCESS;
    }
}
