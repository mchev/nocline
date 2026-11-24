<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The guided "monitor this" recipes (website, scheduled task), shared by the web wizard and the REST API.
 * Authorization is the caller's job: this class only creates records.
 */
class MonitorCreator
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function groupRules(): array
    {
        return [
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'group_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function websiteRules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
            'interval_sec' => ['nullable', 'integer', 'min:30', 'max:86400'],
            'watch_health' => ['nullable', 'boolean'],
            'framework' => ['nullable', Rule::in(SiteFramework::taggable())],
            ...self::groupRules(),
        ];
    }

    /**
     * Add a missing scheme ("example.com" becomes "https://example.com").
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return $url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? "https://{$url}" : $url;
    }

    public function __construct(private readonly DetectedSiteChecks $detected) {}

    /**
     * An existing group id, or the root group of that name (created if missing).
     *
     * @param  array<string, mixed>  $data
     */
    public function resolveGroup(array $data): ?int
    {
        if (! empty($data['group_id'])) {
            return (int) $data['group_id'];
        }

        if (! empty($data['group_name'])) {
            return Group::query()->firstOrCreate(['name' => trim((string) $data['group_name']), 'parent_id' => null])->id;
        }

        return null;
    }

    /**
     * @param  array{url: string, name?: string|null, interval_sec?: int|null, watch_health?: bool|null, framework?: string|null, group_id?: int|null, group_name?: string|null}  $data
     */
    public function website(array $data): Check
    {
        $host = (string) parse_url($data['url'], PHP_URL_HOST);

        $site = Check::create([
            'name' => ($data['name'] ?? null) ?: Str::of($host)->replaceStart('www.', '')->toString(),
            'type' => CheckType::Http,
            'category' => CheckCategory::Website,
            'target' => $data['url'],
            'group_id' => $this->resolveGroup($data),
            'tags' => $this->detected->tags($data['framework'] ?? null) ?: null,
            'interval_sec' => (int) ($data['interval_sec'] ?? 60),
            'timeout_sec' => 10,
            'failure_threshold' => 2,
        ]);

        if (! empty($data['watch_health'])) {
            $this->detected->createHealthCheck($site);
        }

        return $site;
    }

    /**
     * @param  array{name: string, interval_sec: int, group_id?: int|null, group_name?: string|null}  $data
     */
    public function heartbeat(array $data): Check
    {
        return Check::create([
            'name' => $data['name'],
            'type' => CheckType::Heartbeat,
            'category' => CheckCategory::Other,
            'target' => '',
            'group_id' => $this->resolveGroup($data),
            'interval_sec' => $data['interval_sec'],
        ]);
    }
}
