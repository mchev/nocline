<?php

namespace App\Modules\Hosts\Services;

use App\Modules\Alertes\Services\MessageNotifier;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetaPresenter;
use Illuminate\Support\Facades\Cache;

/**
 * Notifies operators when a host starts reporting {@see Host::$agent_meta} reboot_required.
 */
class HostRebootAlertEvaluator
{
    public function __construct(private MessageNotifier $notifier) {}

    /**
     * @param  array<string, mixed>|null  $previousMeta
     */
    public function afterMetadata(Host $host, ?array $previousMeta): void
    {
        if (! config('nocline.reboot_required_alerts')) {
            return;
        }

        $before = (bool) ($previousMeta['reboot_required'] ?? false);
        $after = (bool) ($host->agent_meta['reboot_required'] ?? false);

        if (! $after) {
            Cache::forget($this->immediateKey($host));

            return;
        }

        if ($before) {
            return;
        }

        if (Cache::has($this->immediateKey($host))) {
            return;
        }

        $message = HostMetaPresenter::forHost($host)['reboot_required_message']
            ?? __('A reboot is required to apply a kernel or system update.');

        $this->notifier->broadcast(
            __('Reboot required: :host', ['host' => $host->name]),
            $message,
        );

        Cache::put($this->immediateKey($host), true, now()->addDays(14));
    }

    private function immediateKey(Host $host): string
    {
        return "nocline:host:{$host->id}:reboot-immediate";
    }
}
