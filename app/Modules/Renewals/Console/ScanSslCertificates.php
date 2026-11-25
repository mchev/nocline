<?php

namespace App\Modules\Renewals\Console;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Services\RenewalSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:renewals:scan-ssl')]
#[Description('Record the latest SSL expiry of every https check as an ssl renewal (one row per check, no history)')]
class ScanSslCertificates extends Command
{
    public function handle(RenewalSync $sync): int
    {
        $checks = Check::query()->where('enabled', true)->where('type', CheckType::Http)
            ->where('target', 'like', 'https://%')->orderBy('id')->get();
        $count = $sync->sslFor($checks);

        $this->components->info("{$count} certificate(s) scanned");

        return self::SUCCESS;
    }
}
