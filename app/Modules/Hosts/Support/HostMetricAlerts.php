<?php

namespace App\Modules\Hosts\Support;

use App\Modules\Hosts\Models\Host;

/**
 * Per-host CPU / RAM / disk / load thresholds (percent or load average).
 *
 * @phpstan-type MetricAlertsConfig array{
 *     enabled: bool,
 *     cpu_pct: int,
 *     mem_pct: int,
 *     disk_pct: int,
 *     load_1m: float|null,
 *     sustain_minutes: int,
 * }
 */
class HostMetricAlerts
{
    /**
     * @return MetricAlertsConfig
     */
    public static function defaults(): array
    {
        $defaults = config('nocline.metric_alerts', []);

        return [
            'enabled' => (bool) ($defaults['enabled'] ?? true),
            'cpu_pct' => (int) ($defaults['cpu_pct'] ?? 90),
            'mem_pct' => (int) ($defaults['mem_pct'] ?? 90),
            'disk_pct' => (int) ($defaults['disk_pct'] ?? 90),
            'load_1m' => isset($defaults['load_1m']) ? (float) $defaults['load_1m'] : null,
            'sustain_minutes' => max(1, (int) ($defaults['sustain_minutes'] ?? 5)),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return MetricAlertsConfig
     */
    public static function resolve(?array $stored): array
    {
        $base = self::defaults();

        if ($stored === null || $stored === []) {
            return $base;
        }

        return [
            'enabled' => array_key_exists('enabled', $stored)
                ? (bool) $stored['enabled']
                : $base['enabled'],
            'cpu_pct' => isset($stored['cpu_pct'])
                ? max(1, min(100, (int) $stored['cpu_pct']))
                : $base['cpu_pct'],
            'mem_pct' => isset($stored['mem_pct'])
                ? max(1, min(100, (int) $stored['mem_pct']))
                : $base['mem_pct'],
            'disk_pct' => isset($stored['disk_pct'])
                ? max(1, min(100, (int) $stored['disk_pct']))
                : $base['disk_pct'],
            'load_1m' => array_key_exists('load_1m', $stored)
                ? ($stored['load_1m'] === null || $stored['load_1m'] === ''
                    ? null
                    : max(0.1, (float) $stored['load_1m']))
                : $base['load_1m'],
            'sustain_minutes' => isset($stored['sustain_minutes'])
                ? max(1, min(60, (int) $stored['sustain_minutes']))
                : $base['sustain_minutes'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(): array
    {
        return [
            'metric_alerts' => ['sometimes', 'array'],
            'metric_alerts.enabled' => ['sometimes', 'boolean'],
            'metric_alerts.cpu_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'metric_alerts.mem_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'metric_alerts.disk_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'metric_alerts.load_1m' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:256'],
            'metric_alerts.sustain_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }

    /**
     * @return array{metric_alerts: MetricAlertsConfig, resource_alert: array{active: bool, message: string|null}}
     */
    public static function presentation(Host $host): array
    {
        $settings = self::resolve($host->metric_alerts);
        $state = $host->relationLoaded('resourcesCheck')
            ? $host->resourcesCheck?->state
            : null;
        $down = $state !== null && $state->status->value === 'down';

        return [
            'metric_alerts' => $settings,
            'resource_alert' => [
                'active' => $settings['enabled'] && $down,
                'message' => $down ? $state->last_error : null,
            ],
        ];
    }
}
