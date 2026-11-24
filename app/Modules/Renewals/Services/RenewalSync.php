<?php

namespace App\Modules\Renewals\Services;

use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Jobs\SyncCheckRenewals;
use App\Modules\Renewals\Models\Renewal;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Keeps automatic renewals (domain + SSL) in sync with the monitored http checks.
 */
class RenewalSync
{
    public function __construct(
        private readonly DomainExpiryInspector $domains,
        private readonly CertificateInspector $certificates,
    ) {}

    /**
     * Refresh the domain and SSL renewals of a freshly created/edited check. Safe to call: never throws.
     */
    public static function forCheck(Check $check): void
    {
        try {
            SyncCheckRenewals::dispatch($check->id);
        } catch (Throwable) {
            // Renewals are a convenience: never block the caller.
        }
    }

    /**
     * Refresh every enabled http check. Returns the number of renewals written.
     */
    public function all(): int
    {
        return $this->domainsFor($this->httpChecks()) + $this->sslFor($this->httpChecks());
    }

    public function check(Check $check): int
    {
        if ($check->type !== CheckType::Http || ! $check->enabled) {
            return 0;
        }

        return $this->domainsFor(collect([$check])) + $this->sslFor(collect([$check]));
    }

    /**
     * @param  iterable<Check>  $checks
     */
    public function domainsFor(iterable $checks): int
    {
        $byDomain = [];

        foreach ($checks as $check) {
            $domain = RegistrableDomain::from(parse_url($check->target, PHP_URL_HOST) ?: null);

            if ($domain !== null) {
                $byDomain[$domain] ??= $check;
            }
        }

        $count = 0;

        foreach ($byDomain as $domain => $check) {
            $renewal = Renewal::query()->where('kind', 'domain')->where('label', $domain)->first();

            if ($renewal !== null && $renewal->source !== 'auto') {
                continue;
            }

            $expiry = $this->domains->expiresAt($domain);

            if ($expiry === null) {
                $renewal?->forceFill(['last_checked_at' => now(), 'check_error' => 'Expiry date unavailable'])->save();

                continue;
            }

            ($renewal ?? new Renewal)->forceFill([
                'kind' => 'domain',
                'source' => 'auto',
                'label' => $domain,
                'group_id' => $check->group_id,
                'expires_on' => $expiry->toDateString(),
                'last_checked_at' => now(),
                'check_error' => null,
            ])->save();
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<Check>  $checks
     */
    public function sslFor(iterable $checks): int
    {
        $count = 0;

        foreach ($checks as $check) {
            $parts = parse_url($check->target);

            if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
                continue;
            }

            $renewal = Renewal::query()->where('check_id', $check->id)->where('kind', 'ssl')->first();
            $expiry = $this->certificates->expiresAt($parts['host'], $parts['port'] ?? 443);

            if ($expiry === null) {
                $renewal?->forceFill(['last_checked_at' => now(), 'check_error' => 'Certificate unreadable'])->save();

                continue;
            }

            ($renewal ?? new Renewal)->forceFill([
                'check_id' => $check->id,
                'kind' => 'ssl',
                'source' => 'auto',
                'group_id' => $check->group_id,
                'label' => 'SSL '.$parts['host'],
                'expires_on' => $expiry->toDateString(),
                'last_checked_at' => now(),
                'check_error' => null,
            ])->save();
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, Check>
     */
    private function httpChecks(): Collection
    {
        return Check::query()->where('enabled', true)->where('type', CheckType::Http)->orderBy('id')->get();
    }
}
