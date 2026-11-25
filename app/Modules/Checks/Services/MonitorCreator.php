<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Enums\ObjectStorageLevel;
use App\Modules\Checks\Enums\ObjectStorageProvider;
use App\Modules\Checks\Enums\SiteFramework;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\ObjectStoragePresets;
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

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function objectStorageRules(): array
    {
        return [
            'provider' => ['required', Rule::enum(ObjectStorageProvider::class)],
            'level' => ['required', Rule::enum(ObjectStorageLevel::class)],
            'region' => ['nullable', 'string', 'max:32', 'required_unless:provider,custom', 'required_unless:provider,laravel_cloud'],
            'endpoint_host' => ['nullable', 'string', 'max:255', 'required_if:provider,custom', 'required_if:provider,laravel_cloud'],
            'bucket' => ['nullable', 'string', 'max:63', 'required_if:level,bucket'],
            'health_path' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'interval_sec' => ['nullable', 'integer', 'min:60', 'max:86400'],
            ...self::groupRules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function objectStorage(array $data): Check
    {
        $provider = ObjectStorageProvider::from((string) $data['provider']);
        $level = ObjectStorageLevel::from((string) $data['level']);
        $region = (string) ($data['region'] ?? '');
        $endpoint = isset($data['endpoint_host']) ? (string) $data['endpoint_host'] : null;
        $groupId = $this->resolveGroup($data);
        $tags = ['object-storage', $provider->value];
        $interval = (int) ($data['interval_sec'] ?? 60);
        $bucket = isset($data['bucket']) ? (string) $data['bucket'] : null;
        $healthPath = (string) ($data['health_path'] ?? 'health.txt');

        if ($level === ObjectStorageLevel::Backup) {
            return Check::create([
                'name' => ($data['name'] ?? null) ?: ObjectStoragePresets::defaultMonitorName($provider, $region ?: '—', $level),
                'type' => CheckType::Heartbeat,
                'category' => CheckCategory::Other,
                'target' => '',
                'group_id' => $groupId,
                'tags' => array_merge($tags, ['s3-backup']),
                'interval_sec' => $interval >= 3600 ? $interval : 86400,
            ]);
        }

        $name = ($data['name'] ?? null) ?: ObjectStoragePresets::defaultMonitorName($provider, $region ?: ($endpoint ?? 'custom'), $level, $bucket);

        if ($level === ObjectStorageLevel::Platform) {
            $host = ObjectStoragePresets::endpointHost($provider, $region, $endpoint);

            return Check::create([
                'name' => $name,
                'type' => CheckType::Tcp,
                'category' => CheckCategory::Other,
                'target' => "{$host}:443",
                'group_id' => $groupId,
                'tags' => $tags,
                'interval_sec' => $interval,
                'timeout_sec' => 10,
                'failure_threshold' => 2,
            ]);
        }

        $url = ObjectStoragePresets::bucketObjectUrl($provider, $region, $bucket ?? '', $healthPath, $endpoint);

        return Check::create([
            'name' => $name,
            'type' => CheckType::Http,
            'category' => CheckCategory::Other,
            'target' => $url,
            'group_id' => $groupId,
            'tags' => array_merge($tags, ['s3-bucket']),
            'interval_sec' => $interval,
            'timeout_sec' => 10,
            'failure_threshold' => 2,
        ]);
    }
}
