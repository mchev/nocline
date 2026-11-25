<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\SiteFramework;

/**
 * What Nocline found out about a website address.
 */
final readonly class SiteInspection
{
    /**
     * @param  array<int, string>  $reasons  Plain-language evidence for the detected framework.
     */
    public function __construct(
        public bool $reachable,
        public ?string $finalUrl = null,
        public ?int $statusCode = null,
        public SiteFramework $framework = SiteFramework::Unknown,
        public array $reasons = [],
        public bool $hasHealthEndpoint = false,
        public ?int $certificateDaysLeft = null,
        public ?string $wordpressVersion = null,
    ) {}

    public static function unreachable(): self
    {
        return new self(reachable: false);
    }

    /**
     * Plain-language payload for the wizard.
     *
     * @return array{reachable: bool, final_url: ?string, status_code: ?int, framework: string, reasons: array<int, string>, has_health_endpoint: bool, certificate_days_left: ?int, wordpress_version: ?string, summary: string, suggestions: array<int, array{key: string, label: string, description: string, default: bool}>}
     */
    public function toResponse(): array
    {
        return [
            'reachable' => $this->reachable,
            'final_url' => $this->finalUrl,
            'status_code' => $this->statusCode,
            'framework' => $this->framework->value,
            'reasons' => $this->reasons,
            'has_health_endpoint' => $this->hasHealthEndpoint,
            'certificate_days_left' => $this->certificateDaysLeft,
            'wordpress_version' => $this->wordpressVersion,
            'summary' => $this->summary(),
            'suggestions' => $this->suggestions(),
        ];
    }

    private function summary(): string
    {
        if (! $this->reachable) {
            return __('We could not check this address from here. You can still add it: we will keep trying.');
        }

        return match ($this->framework) {
            SiteFramework::Laravel => __('We could reach your site. It looks like a Laravel site.'),
            SiteFramework::WordPress => __('We could reach your site. It looks like a WordPress site.'),
            SiteFramework::Unknown => __('We could reach your site.'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, default: bool}>
     */
    private function suggestions(): array
    {
        if (! $this->reachable) {
            return [];
        }

        $suggestions = [];

        if ($this->hasHealthEndpoint) {
            $suggestions[] = [
                'key' => 'watch_health',
                'label' => __('Also watch its health page (/up)'),
                'description' => __('Warns you if the application itself is broken, even when the homepage is still served from a cache.'),
                'default' => true,
            ];
        }

        if ($this->certificateDaysLeft !== null) {
            $suggestions[] = [
                'key' => 'certificate',
                'label' => __('Warn me before the SSL certificate expires'),
                'description' => __('Already included: your certificate is valid for :days more days and we will remind you before it ends.', ['days' => $this->certificateDaysLeft]),
                'default' => true,
            ];
        }

        return $suggestions;
    }
}
