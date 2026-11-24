<?php

namespace App\Modules\Hosts\Console;

use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Services\HostProviderEnricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:hosts:refresh-providers')]
#[Description('Guess host cloud provider from public IP (ASN) when the agent did not report it')]
class RefreshHostProviders extends Command
{
    public function handle(HostProviderEnricher $enricher): int
    {
        $updated = 0;
        $total = 0;

        Host::query()
            ->whereNotNull('enrolled_at')
            ->orderBy('id')
            ->chunkById(100, function ($hosts) use ($enricher, &$updated, &$total): void {
                foreach ($hosts as $host) {
                    $total++;

                    if ($enricher->enrich($host)) {
                        $updated++;
                    }
                }
            });

        $this->components->info("Updated provider hint for {$updated} of {$total} host(s).");

        return self::SUCCESS;
    }
}
