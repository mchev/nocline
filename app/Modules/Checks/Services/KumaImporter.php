<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\CheckRules;
use App\Modules\Groups\Models\Group;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Imports Uptime Kuma monitors (JSON backup or the kuma.db SQLite file) as Phare checks.
 * Kuma groups become (nested) Phare groups. Notification channels are not imported; paused monitors are imported disabled.
 */
class KumaImporter
{
    /**
     * @return array<int, array<string, mixed>> raw Kuma monitor rows (with a `tags` list of names)
     */
    public function read(string $path): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 16);

        return str_starts_with($head, 'SQLite format 3') ? $this->readSqlite($path) : $this->readJson($path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJson(string $path): array
    {
        $data = json_decode(File::get($path), true);

        if (! is_array($data) || ! isset($data['monitorList'])) {
            throw new InvalidArgumentException('Not an Uptime Kuma backup (missing "monitorList").');
        }

        return array_map(function (array $m): array {
            $m['tags'] = array_map(fn ($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t, (array) ($m['tags'] ?? []));

            return $m;
        }, array_values((array) $data['monitorList']));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSqlite(string $path): array
    {
        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $rows = $this->fetchAll($pdo, 'SELECT * FROM monitor');
            $tagRows = $this->fetchAll($pdo, 'SELECT mt.monitor_id, t.name FROM monitor_tag mt JOIN tag t ON t.id = mt.tag_id');
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Unreadable Uptime Kuma database: '.$e->getMessage());
        }

        $tags = [];
        foreach ($tagRows as $t) {
            $tags[$t['monitor_id']][] = $t['name'];
        }

        return array_map(function (array $m) use ($tags): array {
            $m['tags'] = $tags[$m['id']] ?? [];

            return $m;
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);

        if ($statement === false) {
            throw new InvalidArgumentException('Unreadable Uptime Kuma database.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monitors
     * @return array{created: array<int, string>, skipped: array<int, array{name: string, reason: string}>}
     */
    public function import(array $monitors, bool $dryRun = false): array
    {
        $result = ['created' => [], 'skipped' => []];
        $byId = [];
        foreach ($monitors as $m) {
            $byId[$m['id'] ?? null] = $m;
        }

        foreach ($monitors as $m) {
            $name = trim((string) ($m['name'] ?? ''));
            $mapped = $this->map($m);

            if (is_string($mapped)) {
                $result['skipped'][] = ['name' => $name, 'reason' => $mapped];

                continue;
            }

            if (Check::query()->where('name', $name)->where('target', $mapped['target'])->exists()) {
                $result['skipped'][] = ['name' => $name, 'reason' => 'already exists'];

                continue;
            }

            if (! $dryRun) {
                Check::create($mapped + ['group_id' => $this->groupFor($m, $byId)]);
            }

            $result['created'][] = $name;
        }

        return $result;
    }

    /**
     * Find or create the nested Phare group that mirrors the monitor's chain of Kuma groups.
     *
     * @param  array<string, mixed>  $m
     * @param  array<int|string|null, array<string, mixed>>  $byId
     */
    private function groupFor(array $m, array $byId): ?int
    {
        $names = [];
        $parent = $m['parent'] ?? null;

        while ($parent && isset($byId[$parent]) && count($names) < 10) {
            array_unshift($names, mb_substr(trim((string) ($byId[$parent]['name'] ?? '')), 0, 255));
            $parent = $byId[$parent]['parent'] ?? null;
        }

        $groupId = null;
        foreach (array_filter($names) as $name) {
            $groupId = Group::query()->firstOrCreate(['parent_id' => $groupId, 'name' => $name])->id;
        }

        return $groupId;
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>|string attributes for a Check, or the reason it is skipped
     */
    private function map(array $m): array|string
    {
        $type = (string) ($m['type'] ?? '');
        $name = trim((string) ($m['name'] ?? ''));
        $config = [];

        if ($name === '') {
            return 'no name';
        }

        switch ($type) {
            case 'http':
            case 'keyword':
            case 'json-query':
                $checkType = CheckType::Http;
                $category = CheckCategory::Website;
                $target = (string) ($m['url'] ?? '');
                if ($type === 'keyword' && ! empty($m['keyword'])) {
                    $config['keyword'] = (string) $m['keyword'];
                }
                break;
            case 'port':
                $checkType = CheckType::Tcp;
                $category = CheckCategory::Server;
                $target = ($m['hostname'] ?? '').':'.($m['port'] ?? '');
                break;
            case 'ping':
                $checkType = CheckType::Icmp;
                $category = CheckCategory::Network;
                $target = (string) ($m['hostname'] ?? '');
                break;
            case 'dns':
                $checkType = CheckType::Dns;
                $category = CheckCategory::Dns;
                $target = (string) ($m['hostname'] ?? '');
                break;
            case 'push':
                $checkType = CheckType::Heartbeat;
                $category = CheckCategory::Other;
                $target = '';
                break;
            case 'group':
                return 'group (becomes a group of its monitors)';
            default:
                return "unsupported type \"{$type}\"";
        }

        if (($target === '' && $checkType !== CheckType::Heartbeat) || $target === ':') {
            return 'no target';
        }

        $tags = array_values(array_unique(array_filter(array_map(
            fn ($t) => mb_substr(trim((string) $t), 0, 50),
            (array) ($m['tags'] ?? []),
        ))));

        return [
            'name' => mb_substr($name, 0, 255),
            'type' => $checkType,
            'category' => $category,
            'target' => mb_substr($target, 0, 500),
            'interval_sec' => max(30, min($checkType === CheckType::Heartbeat ? CheckRules::HEARTBEAT_MAX_INTERVAL_SEC : 86400, (int) ($m['interval'] ?? 60))),
            'timeout_sec' => max(1, min(60, (int) round((float) ($m['timeout'] ?? 10)))),
            'failure_threshold' => max(1, min(10, (int) ($m['maxretries'] ?? 0) + 1)),
            'config' => $config ?: null,
            'tags' => array_slice($tags, 0, 20),
            'enabled' => (bool) ($m['active'] ?? true),
            'execution' => in_array($checkType, [CheckType::Icmp, CheckType::Dns], true) ? CheckExecution::Probe : CheckExecution::Hub,
        ];
    }
}
