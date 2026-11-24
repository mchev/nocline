<?php

namespace App\Modules\Renewals\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds a domain's registration expiry through RDAP (public, no key). Never throws: null means unknown.
 */
class DomainExpiryInspector
{
    public function expiresAt(string $host): ?CarbonImmutable
    {
        $domain = RegistrableDomain::from($host);

        if ($domain === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)->connectTimeout(10)->withOptions(['allow_redirects' => ['max' => 5]])
                ->acceptJson()->get('https://rdap.org/domain/'.$domain);

            if (! $response->successful()) {
                return null;
            }

            foreach ((array) $response->json('events', []) as $event) {
                if (is_array($event) && ($event['eventAction'] ?? null) === 'expiration' && is_string($event['eventDate'] ?? null)) {
                    return CarbonImmutable::parse($event['eventDate']);
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
