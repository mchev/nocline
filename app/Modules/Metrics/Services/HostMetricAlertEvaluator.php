<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Services\HostResourceCheckProvisioner;
use App\Modules\Hosts\Support\HostMetricAlerts;
use App\Modules\Metrics\Models\MetricRollup;
use Carbon\CarbonInterface;

/**
 * After each metrics ingest, opens or resolves the host "Server load" check when rollups breach thresholds.
 */
class HostMetricAlertEvaluator
{
    public function __construct(
        private readonly HostResourceCheckProvisioner $provisioner,
        private readonly CheckResultRecorder $recorder,
    ) {}

    public function evaluate(Host $host): void
    {
        if ($host->enrolled_at === null) {
            return;
        }

        $settings = HostMetricAlerts::resolve($host->metric_alerts);
        $check = $this->provisioner->ensure($host);

        if ($check === null) {
            return;
        }

        if (! $settings['enabled']) {
            $this->recorder->record($check, true, null, null, ['metric_alerts' => 'disabled']);

            return;
        }

        $breaches = $this->sustainedBreaches($host, $settings);

        if ($breaches === []) {
            $this->recorder->record($check, true, null, null, ['breaches' => []]);

            return;
        }

        $error = $this->formatError($breaches, $settings);
        $this->recorder->record($check, false, null, $error, ['breaches' => $breaches]);
    }

    /**
     * @param  array{enabled: bool, cpu_pct: int, mem_pct: int, disk_pct: int, load_1m: float|null, sustain_minutes: int}  $settings
     * @return list<array{key: string, label: string, value: float, threshold: float}>
     */
    private function sustainedBreaches(Host $host, array $settings): array
    {
        $minutes = $settings['sustain_minutes'];
        $since = now()->subMinutes($minutes - 1)->startOfMinute();

        $rules = [
            'cpu' => ['metric_keys' => ['cpu.usage_pct', 'cpu.usage'], 'threshold' => (float) $settings['cpu_pct'], 'label' => 'CPU'],
            'mem' => ['metric_keys' => ['mem.used_pct', 'mem.usage'], 'threshold' => (float) $settings['mem_pct'], 'label' => __('RAM')],
            'disk' => ['metric_keys' => ['disk.used_pct', 'disk.usage'], 'threshold' => (float) $settings['disk_pct'], 'label' => __('Disk')],
        ];

        $breaches = [];

        foreach ($rules as $key => $rule) {
            $value = $this->sustainedMax($host, $rule['metric_keys'], $rule['threshold'], $since, $minutes);

            if ($value !== null) {
                $breaches[] = [
                    'key' => $key,
                    'label' => $rule['label'],
                    'value' => $value,
                    'threshold' => $rule['threshold'],
                ];
            }
        }

        if ($settings['load_1m'] !== null) {
            $value = $this->sustainedMax($host, ['load.1m'], $settings['load_1m'], $since, $minutes);

            if ($value !== null) {
                $breaches[] = [
                    'key' => 'load',
                    'label' => __('Load (1 min)'),
                    'value' => $value,
                    'threshold' => $settings['load_1m'],
                ];
            }
        }

        return $breaches;
    }

    /**
     * @param  list<string>  $metricKeys
     */
    private function sustainedMax(
        Host $host,
        array $metricKeys,
        float $threshold,
        CarbonInterface $since,
        int $minutes,
    ): ?float {
        $rollups = MetricRollup::query()
            ->where('host_id', $host->id)
            ->whereIn('metric_key', $metricKeys)
            ->where('bucket_size', MetricRollup::BUCKET_1M)
            ->where('bucket_start', '>=', $since)
            ->orderBy('bucket_start')
            ->get(['bucket_start', 'max', 'avg']);

        /** @var array<int, float> $byMinute */
        $byMinute = [];

        foreach ($rollups as $rollup) {
            $ts = (int) $rollup->bucket_start->startOfMinute()->timestamp;
            $point = (float) ($rollup->max ?? $rollup->avg);
            $byMinute[$ts] = isset($byMinute[$ts]) ? max($byMinute[$ts], $point) : $point;
        }

        if (count($byMinute) < $minutes) {
            return null;
        }

        $peak = 0.0;

        foreach ($byMinute as $point) {
            if ($point < $threshold) {
                return null;
            }

            $peak = max($peak, $point);
        }

        return round($peak, 1);
    }

    /**
     * @param  list<array{key: string, label: string, value: float, threshold: float}>  $breaches
     * @param  array{enabled: bool, cpu_pct: int, mem_pct: int, disk_pct: int, load_1m: float|null, sustain_minutes: int}  $settings
     */
    private function formatError(array $breaches, array $settings): string
    {
        $parts = [];

        foreach ($breaches as $breach) {
            if ($breach['key'] === 'load') {
                $parts[] = sprintf('%s %.1f (>%s)', $breach['label'], $breach['value'], rtrim(rtrim((string) $breach['threshold'], '0'), '.'));
            } else {
                $parts[] = sprintf('%s %.0f%% (>%d%%)', $breach['label'], $breach['value'], (int) $breach['threshold']);
            }
        }

        return __('High server load: :details (for :minutes min)', [
            'details' => implode(', ', $parts),
            'minutes' => $settings['sustain_minutes'],
        ]);
    }
}
