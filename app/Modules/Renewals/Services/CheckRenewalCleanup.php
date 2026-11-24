<?php

namespace App\Modules\Renewals\Services;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Models\Renewal;

/**
 * Drops automatic renewals when a monitored site is removed.
 */
final class CheckRenewalCleanup
{
    public function forDeletedCheck(Check $check): void
    {
        Renewal::query()->where('check_id', $check->id)->delete();

        if ($check->type !== CheckType::Http) {
            return;
        }

        $domain = RegistrableDomain::from(parse_url($check->target, PHP_URL_HOST) ?: null);

        if ($domain === null) {
            return;
        }

        if ($this->anotherHttpCheckMonitorsDomain($check->id, $domain)) {
            return;
        }

        Renewal::query()
            ->where('kind', 'domain')
            ->where('source', 'auto')
            ->where('label', $domain)
            ->delete();
    }

    private function anotherHttpCheckMonitorsDomain(int $exceptId, string $domain): bool
    {
        return Check::query()
            ->where('type', CheckType::Http)
            ->where('id', '!=', $exceptId)
            ->whereNotNull('target')
            ->get(['id', 'target'])
            ->contains(function (Check $peer) use ($domain): bool {
                return RegistrableDomain::from(parse_url($peer->target, PHP_URL_HOST) ?: null) === $domain;
            });
    }
}
