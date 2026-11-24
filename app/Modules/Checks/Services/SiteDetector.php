<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Renewals\Services\CertificateInspector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Looks at a website (safely, with a few GET requests) to propose sensible monitoring.
 *
 * The hub makes these requests on behalf of a user, so every hop is checked by the NetworkGuard
 * (SSRF): the host is resolved, each resulting IP must be public, and the connection is pinned
 * to the validated IP so a DNS answer cannot change between the check and the request.
 */
class SiteDetector
{
    private const array ALLOWED_PORTS = [80, 443, 8080, 8443];

    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 262144;

    private const int TIMEOUT_SECONDS = 5;

    private const int FRAMEWORK_THRESHOLD = 3;

    public function __construct(
        private readonly NetworkGuard $guard,
        private readonly HostResolver $resolver,
        private readonly CertificateInspector $certificates,
    ) {}

    /**
     * Safe HTTP GET for other detectors (e.g. site favicon).
     *
     * @return array{url: string, status: int, headers: array<string, array<int, string>>, body: string}|null
     */
    public function fetchPage(string $url, int $maxRedirects = self::MAX_REDIRECTS): ?array
    {
        return $this->fetch($url, $maxRedirects);
    }

    public function inspect(string $url): SiteInspection
    {
        $page = $this->fetch($url, self::MAX_REDIRECTS);

        if ($page === null) {
            return SiteInspection::unreachable();
        }

        $origin = $this->origin($page['url']);
        $laravel = $this->laravelSignals($page);
        $wordpress = $this->wordpressSignals($page);

        $hasHealthEndpoint = false;
        $healthPage = $origin !== null ? $this->fetch($origin.'/up', 0) : null;

        if ($healthPage !== null && $this->looksLikeLaravelHealthPage($healthPage) && $this->isNotCatchAll($origin, $healthPage)) {
            $laravel['score'] += 3;
            $laravel['reasons'][] = __('It answers on its health page (/up).');
            $hasHealthEndpoint = true;
        }

        if ($origin !== null && $laravel['score'] < self::FRAMEWORK_THRESHOLD && $wordpress['score'] < self::FRAMEWORK_THRESHOLD) {
            $api = $this->fetch($origin.'/wp-json/', 0);

            if ($api !== null && $api['status'] === 200 && str_contains($api['body'], '"namespaces"')) {
                $wordpress['score'] += 3;
                $wordpress['reasons'][] = __('It exposes the WordPress API.');
            }
        }

        $framework = match (true) {
            $wordpress['score'] >= self::FRAMEWORK_THRESHOLD && $wordpress['score'] > $laravel['score'] => SiteFramework::WordPress,
            $laravel['score'] >= self::FRAMEWORK_THRESHOLD => SiteFramework::Laravel,
            default => SiteFramework::Unknown,
        };

        return new SiteInspection(
            reachable: true,
            finalUrl: $page['url'],
            statusCode: $page['status'],
            framework: $framework,
            reasons: match ($framework) {
                SiteFramework::Laravel => $laravel['reasons'],
                SiteFramework::WordPress => $wordpress['reasons'],
                SiteFramework::Unknown => [],
            },
            hasHealthEndpoint: $hasHealthEndpoint,
            certificateDaysLeft: $this->certificateDaysLeft($page['url']),
        );
    }

    /**
     * One guarded GET, following only same-site redirects (and http to https upgrades).
     *
     * @return array{url: string, status: int, headers: array<string, array<int, string>>, body: string}|null
     */
    private function fetch(string $url, int $maxRedirects): ?array
    {
        $current = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $target = $this->guardedTarget($current);

            if ($target === null) {
                return null;
            }

            $response = $this->request($current, $target);

            if ($response === null) {
                return null;
            }

            $page = [
                'url' => $current,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $this->readBody($response),
            ];

            $location = $response->redirect() ? $response->header('Location') : '';
            $next = $location !== '' ? $this->resolveLocation($current, $location) : null;

            if ($next === null || ! $this->isSameSite($current, $next) || $hop === $maxRedirects) {
                return $page;
            }

            $current = $next;
        }

        return null;
    }

    /**
     * @param  array{host: string, port: int, ip: ?string}  $target
     */
    private function request(string $url, array $target): ?Response
    {
        $options = ['allow_redirects' => false];

        if ($target['ip'] !== null) {
            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"],
            ];
        }

        try {
            return Http::withUserAgent('Phare-Detect/'.config('phare.version'))
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->withOptions($options)
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function readBody(Response $response): string
    {
        $body = $response->body();

        return strlen($body) > self::MAX_BODY_BYTES
            ? substr($body, 0, self::MAX_BODY_BYTES)
            : $body;
    }

    /**
     * Validates a URL and resolves its host; null means "do not contact".
     *
     * @return array{host: string, port: int, ip: ?string}|null
     */
    private function guardedTarget(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $allowPrivate = (bool) config('phare.detect_allow_private_networks');

        if ($this->guard->isObfuscatedIpv4($host)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $allowPrivate || $this->guard->isPublicAddress($host) ? ['host' => $host, 'port' => $port, 'ip' => null] : null;
        }

        $ips = $this->resolver->resolve($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! $allowPrivate && ! $this->guard->isPublicAddress($ip)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    private function resolveLocation(string $base, string $location): ?string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $authority = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        return $authority.'/'.$location;
    }

    /**
     * Same site: same host (ignoring "www."), and never a downgrade from https to http.
     */
    private function isSameSite(string $from, string $to): bool
    {
        $a = parse_url($from);
        $b = parse_url($to);

        if ($a === false || $b === false || ! isset($a['host'], $b['host'])) {
            return false;
        }

        $sameHost = Str::of($a['host'])->lower()->replaceStart('www.', '')->toString() === Str::of($b['host'])->lower()->replaceStart('www.', '')->toString();
        $downgrade = strtolower($a['scheme'] ?? '') === 'https' && strtolower($b['scheme'] ?? '') === 'http';

        return $sameHost && ! $downgrade;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @param  array{url: string, status: int, headers: array<string, array<int, string>>, body: string}  $page
     * @return array{score: int, reasons: array<int, string>}
     */
    private function laravelSignals(array $page): array
    {
        $score = 0;
        $reasons = [];
        $cookies = $this->cookieNames($page['headers']);
        $body = $page['body'];

        if (in_array('laravel_session', $cookies, true)) {
            $score += 3;
            $reasons[] = __('It sets the Laravel session cookie.');
        }

        if (in_array('XSRF-TOKEN', $cookies, true)) {
            $score += 1;
            $reasons[] = __('It sets the Laravel security cookie.');
        }

        if (preg_match('/\bdata-page=|\bwire:|livewire/i', $body) === 1 || $this->hasHeader($page['headers'], 'X-Inertia') || $this->hasHeader($page['headers'], 'X-Livewire')) {
            $score += 2;
            $reasons[] = __('Its pages use Inertia or Livewire.');
        }

        if (str_contains($body, '/build/assets/')) {
            $score += 1;
            $reasons[] = __('Its assets are built with Vite.');
        }

        if (stripos($page['headers']['X-Powered-By'][0] ?? '', 'php') !== false) {
            $score += 1;
            $reasons[] = __('It runs on PHP.');
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * @param  array{url: string, status: int, headers: array<string, array<int, string>>, body: string}  $page
     * @return array{score: int, reasons: array<int, string>}
     */
    private function wordpressSignals(array $page): array
    {
        $score = 0;
        $reasons = [];

        if (str_contains($page['body'], '/wp-content/') || str_contains($page['body'], '/wp-includes/')) {
            $score += 3;
            $reasons[] = __('Its pages load WordPress files.');
        }

        if (preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress/i', $page['body']) === 1) {
            $score += 3;
            $reasons[] = __('It announces itself as WordPress.');
        }

        if (preg_match('#rel=["\']https://api\.w\.org/#i', $page['body']) === 1 || str_contains(implode(' ', $page['headers']['Link'] ?? []), 'api.w.org')) {
            $score += 2;
            $reasons[] = __('It exposes the WordPress API.');
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * @param  array{url: string, status: int, headers: array<string, array<int, string>>, body: string}  $page
     */
    private function looksLikeLaravelHealthPage(array $page): bool
    {
        return $page['status'] === 200 && preg_match('/Application up/i', $page['body']) === 1;
    }

    /**
     * An application answering 200 with the same page for every path would fake a health endpoint.
     *
     * @param  array{url: string, status: int, headers: array<string, array<int, string>>, body: string}  $healthPage
     */
    private function isNotCatchAll(string $origin, array $healthPage): bool
    {
        $random = $this->fetch($origin.'/phare-'.bin2hex(random_bytes(6)), 0);

        if ($random === null || $random['status'] !== 200) {
            return true;
        }

        return $random['body'] !== $healthPage['body'] && preg_match('/Application up/i', $random['body']) !== 1;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<int, string>
     */
    private function cookieNames(array $headers): array
    {
        $names = [];

        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'Set-Cookie') !== 0) {
                continue;
            }

            foreach ($values as $cookie) {
                $names[] = trim(urldecode(explode('=', explode(';', $cookie)[0])[0]));
            }
        }

        return $names;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    private function hasHeader(array $headers, string $wanted): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp((string) $name, $wanted) === 0) {
                return true;
            }
        }

        return false;
    }

    private function certificateDaysLeft(string $url): ?int
    {
        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])) {
            return null;
        }

        $expiresAt = $this->certificates->expiresAt(trim($parts['host'], '[]'), $parts['port'] ?? 443, self::TIMEOUT_SECONDS);

        return $expiresAt !== null ? (int) now()->diffInDays($expiresAt, false) : null;
    }
}
