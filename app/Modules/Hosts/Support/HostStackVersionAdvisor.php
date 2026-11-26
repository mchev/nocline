<?php

namespace App\Modules\Hosts\Support;

use App\Modules\Hosts\Services\EndOfLifeCatalog;
use Illuminate\Support\Carbon;

/**
 * Flags stack versions using cached endoflife.date release cycles (not live CVE data).
 */
class HostStackVersionAdvisor
{
    /** @var array<string, string> */
    private const PRODUCT_LABELS = [
        'php' => 'PHP',
        'node' => 'Node.js',
        'mysql' => 'MySQL',
        'mariadb' => 'MariaDB',
        'postgresql' => 'PostgreSQL',
        'redis' => 'Redis',
        'nginx' => 'Nginx',
        'docker' => 'Docker',
    ];

    public function __construct(
        private readonly EndOfLifeCatalog $catalog,
    ) {}

    /**
     * @param  array<string, string>|null  $stack
     * @return array<string, string> stack key => localized warning (tooltip)
     */
    public function warnings(?array $stack, ?Carbon $today = null): array
    {
        if ($stack === null || $stack === [] || ! config('nocline.stack_eol_check') || $this->catalog->catalog() === null) {
            return [];
        }

        $today ??= Carbon::today();
        $out = [];

        foreach ($stack as $key => $version) {
            if ($version === '' || ! isset(EndOfLifeCatalog::STACK_TO_PRODUCT[$key])) {
                continue;
            }

            $reason = $this->reasonForKey($key, $version, $today);

            if ($reason !== null) {
                $out[$key] = $reason;
            }
        }

        return $out;
    }

    private function reasonForKey(string $key, string $version, Carbon $today): ?string
    {
        $label = __(self::PRODUCT_LABELS[$key]);
        $releases = $this->catalog->releasesForStackKey($key);
        $cycle = $this->catalog->matchRelease($releases, $version);

        if ($cycle === null) {
            return null;
        }

        if ($cycle['isEol'] || ($cycle['eolFrom'] !== null && $today->greaterThan(Carbon::parse($cycle['eolFrom'])))) {
            return self::message($label, 'eol');
        }

        if ($key === 'node' && ! $cycle['isLts'] && $cycle['isMaintained']) {
            return self::message($label, 'non_lts');
        }

        $latest = $cycle['latest'];

        if (is_string($latest) && $latest !== '' && StackVersion::isOlderThan($version, $latest)) {
            return self::message($label, 'stale');
        }

        return null;
    }

    /**
     * @param  'eol'|'non_lts'|'stale'  $kind
     */
    private static function message(string $product, string $kind): string
    {
        return match ($kind) {
            'eol' => __(':product is no longer receiving security updates.', ['product' => $product]),
            'non_lts' => __(':product is not an LTS release — plan an upgrade.', ['product' => $product]),
            'stale' => __(':product version is outdated — check for security patches.', ['product' => $product]),
        };
    }
}
