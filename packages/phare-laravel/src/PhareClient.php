<?php

namespace Phare\Laravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends heartbeat pings to a Phare hub. It never throws and never waits longer than the configured
 * timeout, so a hub outage cannot break or slow the host application.
 */
final class PhareClient
{
    /**
     * @param  array<string, string>  $heartbeats  name => token
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly ?string $baseUrl,
        private readonly ?string $defaultToken = null,
        private readonly array $heartbeats = [],
        private readonly int $timeout = 3,
    ) {}

    /**
     * Tell Phare the job ran fine. Returns whether the hub acknowledged it.
     */
    public function heartbeat(?string $tokenOrName = null, ?string $message = null): bool
    {
        return $this->send($this->urlFor($tokenOrName), $message);
    }

    /**
     * Tell Phare the job failed (opens an incident right away); the optional message shows in Phare.
     */
    public function fail(?string $tokenOrName = null, ?string $message = null): bool
    {
        return $this->send($this->urlFor($tokenOrName, fail: true), $message);
    }

    /**
     * The ping address for a token or a configured name; null when nothing is configured.
     */
    public function urlFor(?string $tokenOrName = null, bool $fail = false): ?string
    {
        $token = $tokenOrName === null ? $this->defaultToken : ($this->heartbeats[$tokenOrName] ?? $tokenOrName);

        if ($this->baseUrl === null || $this->baseUrl === '' || $token === null || $token === '') {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/api/v1/heartbeat/'.rawurlencode($token).($fail ? '/fail' : '');
    }

    private function send(?string $url, ?string $message): bool
    {
        if ($url === null) {
            $this->logger->debug('Phare ping skipped: PHARE_URL or the heartbeat token is not configured.');

            return false;
        }

        try {
            return $this->http
                ->timeout($this->timeout)
                ->connectTimeout($this->timeout)
                ->withoutRedirecting()
                ->get($url, $message === null || $message === '' ? [] : ['msg' => mb_substr($message, 0, 200)])
                ->successful();
        } catch (Throwable $e) {
            // The exception message embeds the URL (and so the secret token): log the class only.
            $this->logger->debug('Phare ping failed: '.$e::class);

            return false;
        }
    }
}
