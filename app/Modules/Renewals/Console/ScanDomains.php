<?php

namespace App\Modules\Renewals\Console;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Services\RenewalSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:renewals:scan-domains')]
#[Description('Record the registration expiry (RDAP) of the domain of every enabled http check, one renewal per domain')]
class ScanDomains extends Command
{
    public function handle(RenewalSync $sync): int
    {
        $checks = Check::query()->where('enabled', true)->where('type', CheckType::Http)->orderBy('id')->get();
        $count = $sync->domainsFor($checks);

        $this->components->info("{$count} domain(s) scanned");

        return self::SUCCESS;
    }
}
