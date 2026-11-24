<?php

namespace App\Console\Commands\Phare;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:demo:seed')]
#[Description('Seed a full demo dataset (groups, hosts, checks, status page, wallboard…) without wiping the database')]
class SeedDemoCommand extends Command
{
    public function handle(DemoSeeder $seeder): int
    {
        $this->components->info('Seeding demo data (additive / idempotent by name)…');
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
