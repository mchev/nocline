<?php

namespace App\Modules\Alertes\Services;

use App\Models\AppSetting;
use App\Modules\Alertes\Enums\RenewalNotifyMode;
use App\Modules\Renewals\Models\Renewal;

/**
 * Instance-wide rules for what gets pushed to notification channels.
 */
class NotificationPreferences
{
    public const string STORAGE_KEY = 'notification_preferences';

    /**
     * @return array{
     *     incidents: array{opened: bool, resolved: bool},
     *     renewals: array{ssl: string, domain: string, contract: string, other: string}
     * }
     */
    public function all(): array
    {
        $stored = AppSetting::query()->find(self::STORAGE_KEY)?->value;

        return $this->mergeWithDefaults(is_array($stored) ? $stored : []);
    }

    /**
     * @param  array{incidents?: array<string, mixed>, renewals?: array<string, mixed>}  $input
     */
    public function save(array $input): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::STORAGE_KEY],
            ['value' => $this->mergeWithDefaults($input)],
        );
    }

    public function incidentNotificationsEnabled(string $kind): bool
    {
        $prefs = $this->all();

        return match ($kind) {
            'resolved' => $prefs['incidents']['resolved'],
            default => $prefs['incidents']['opened'],
        };
    }

    public function renewalMode(string $kind): RenewalNotifyMode
    {
        $key = in_array($kind, Renewal::NOTIFY_KINDS, true) ? $kind : 'other';

        $mode = $this->all()['renewals'][$key];

        return RenewalNotifyMode::tryFrom($mode) ?? RenewalNotifyMode::Thresholds;
    }

    public function renewalNotificationsEnabled(Renewal $renewal, int $daysRemaining): bool
    {
        return match ($this->renewalMode($renewal->kind)) {
            RenewalNotifyMode::Off => false,
            RenewalNotifyMode::ExpiredOnly => $daysRemaining < 0,
            RenewalNotifyMode::Thresholds => true,
        };
    }

    /**
     * @return array<int, RenewalNotifyMode>
     */
    public function renewalModeOptions(): array
    {
        return [
            RenewalNotifyMode::Off,
            RenewalNotifyMode::ExpiredOnly,
            RenewalNotifyMode::Thresholds,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     incidents: array{opened: bool, resolved: bool},
     *     renewals: array{ssl: string, domain: string, contract: string, other: string}
     * }
     */
    private function mergeWithDefaults(array $input): array
    {
        $defaults = [
            'incidents' => [
                'opened' => true,
                'resolved' => true,
            ],
            'renewals' => [
                'ssl' => RenewalNotifyMode::Thresholds->value,
                'domain' => RenewalNotifyMode::Thresholds->value,
                'contract' => RenewalNotifyMode::Thresholds->value,
                'other' => RenewalNotifyMode::Thresholds->value,
            ],
        ];

        $incidents = is_array($input['incidents'] ?? null) ? $input['incidents'] : [];
        $renewals = is_array($input['renewals'] ?? null) ? $input['renewals'] : [];

        $normalizedRenewals = [];

        foreach (Renewal::NOTIFY_KINDS as $kind) {
            $raw = (string) ($renewals[$kind] ?? $defaults['renewals'][$kind]);
            $mode = RenewalNotifyMode::tryFrom($raw);
            $normalizedRenewals[$kind] = $mode !== null
                ? $mode->value
                : RenewalNotifyMode::Thresholds->value;
        }

        return [
            'incidents' => [
                'opened' => (bool) ($incidents['opened'] ?? $defaults['incidents']['opened']),
                'resolved' => (bool) ($incidents['resolved'] ?? $defaults['incidents']['resolved']),
            ],
            'renewals' => $normalizedRenewals,
        ];
    }
}
