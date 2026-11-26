<?php

namespace App\Modules\Hosts\Console;

use App\Modules\Hosts\Services\EndOfLifeCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:stack-eol:refresh')]
#[Description('Refresh cached end-of-life data for server stack versions (endoflife.date)')]
class RefreshStackEndOfLife extends Command
{
    public function handle(EndOfLifeCatalog $catalog): int
    {
        if (! config('nocline.stack_eol_check')) {
            $this->components->info('Stack EOL check is disabled (NOCLINE_STACK_EOL_CHECK=false).');

            return self::SUCCESS;
        }

        if ($catalog->refresh()) {
            $cached = $catalog->catalog();
            $count = is_array($cached['products'] ?? null) ? count($cached['products']) : 0;
            $this->components->info("Cached EOL data for {$count} product(s) from endoflife.date.");

            return self::SUCCESS;
        }

        $this->components->warn('Could not refresh stack EOL data (offline, rate limit, or API error). Existing cache kept.');

        return self::SUCCESS;
    }
}
