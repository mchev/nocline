<?php

namespace Nocline\Laravel\Console;

use Illuminate\Console\Command;
use Nocline\Laravel\NoclineClient;

class PingCommand extends Command
{
    protected $signature = 'nocline:ping
        {token? : Heartbeat token or configured name (defaults to NOCLINE_HEARTBEAT_TOKEN)}
        {--fail : Report a failure instead of a success}
        {--message= : Short text shown in Nocline when reporting a failure}';

    protected $description = 'Send a heartbeat ping to Nocline';

    public function handle(NoclineClient $nocline): int
    {
        $token = $this->argument('token');
        $token = is_string($token) ? $token : null;
        $message = $this->option('message');
        $message = is_string($message) ? $message : null;

        $delivered = $this->option('fail') ? $nocline->fail($token, $message) : $nocline->heartbeat($token, $message);

        if (! $delivered) {
            $this->components->warn('The ping was not delivered (check NOCLINE_URL and the token).');

            return self::FAILURE;
        }

        $this->components->info('Ping sent.');

        return self::SUCCESS;
    }
}
