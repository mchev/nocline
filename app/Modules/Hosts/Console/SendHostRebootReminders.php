<?php

namespace App\Modules\Hosts\Console;

use App\Modules\Alertes\Services\MessageNotifier;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetaPresenter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('nocline:hosts:reboot-remind')]
#[Description('Daily reminder for servers that still need a reboot after a kernel update')]
class SendHostRebootReminders extends Command
{
    public function handle(MessageNotifier $notifier): int
    {
        if (! config('nocline.reboot_required_alerts') || ! config('nocline.reboot_required_daily_remind')) {
            $this->components->info('Reboot reminders disabled');

            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $cacheKey = 'nocline:reboot-daily:'.$today;

        if (Cache::has($cacheKey)) {
            $this->components->info('Daily reboot reminder already sent');

            return self::SUCCESS;
        }

        $lines = [];

        Host::query()
            ->whereNotNull('enrolled_at')
            ->orderBy('name')
            ->each(function (Host $host) use (&$lines): void {
                $presentation = HostMetaPresenter::forHost($host);

                if (! $presentation['reboot_required']) {
                    return;
                }

                $lines[] = '• '.$host->name.': '.($presentation['reboot_required_message'] ?? '');
            });

        if ($lines === []) {
            $this->components->info('No servers pending reboot');

            return self::SUCCESS;
        }

        $body = implode("\n", $lines);

        $notifier->broadcast(
            __(':count server(s) need a reboot for pending updates', ['count' => count($lines)]),
            $body,
        );

        Cache::put($cacheKey, true, now()->addDay());

        $this->components->info(count($lines).' reminder(s) sent');

        return self::SUCCESS;
    }
}
