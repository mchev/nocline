<?php

namespace App\Modules\Renewals\Console;

use App\Modules\Alertes\Enums\RenewalNotifyMode;
use App\Modules\Alertes\Services\MessageNotifier;
use App\Modules\Alertes\Services\NotificationPreferences;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Renewals\Models\RenewalReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nocline:renewals:remind')]
#[Description('Send one reminder per crossed threshold (30/14/7/1 days by default) for upcoming expirations')]
class SendRenewalReminders extends Command
{
    public function handle(MessageNotifier $notifier, NotificationPreferences $preferences): int
    {
        $sent = 0;

        Renewal::query()
            ->where('expires_on', '<=', now()->addDays(max(Renewal::DEFAULT_REMIND_DAYS) + 60))
            ->each(function (Renewal $renewal) use ($notifier, $preferences, &$sent): void {
                $days = $renewal->daysRemaining();
                $mode = $preferences->renewalMode($renewal->kind);

                if ($mode === RenewalNotifyMode::Off) {
                    return;
                }

                if ($mode === RenewalNotifyMode::ExpiredOnly) {
                    if ($days >= 0) {
                        return;
                    }

                    $already = RenewalReminder::query()
                        ->where('renewal_id', $renewal->id)
                        ->where('expires_on', $renewal->expires_on->toDateString())
                        ->where('days_before', 0)
                        ->exists();

                    if ($already) {
                        return;
                    }

                    RenewalReminder::query()->create([
                        'renewal_id' => $renewal->id,
                        'expires_on' => $renewal->expires_on->toDateString(),
                        'days_before' => 0,
                        'sent_at' => now(),
                    ]);

                    $this->broadcastRenewal($notifier, $renewal, $days);
                    $sent++;

                    return;
                }

                $crossed = array_filter($renewal->thresholds(), fn (int $t) => $days <= $t);

                if ($crossed === []) {
                    return;
                }

                $already = RenewalReminder::query()
                    ->where('renewal_id', $renewal->id)->where('expires_on', $renewal->expires_on->toDateString())
                    ->pluck('days_before')->all();

                $pending = array_diff($crossed, $already);

                if ($pending === []) {
                    return;
                }

                // Claim every crossed threshold first so a late start never spams several mails.
                foreach ($pending as $threshold) {
                    RenewalReminder::query()->firstOrCreate(
                        ['renewal_id' => $renewal->id, 'expires_on' => $renewal->expires_on->toDateString(), 'days_before' => $threshold],
                        ['sent_at' => now()],
                    );
                }

                $this->broadcastRenewal($notifier, $renewal, $days);
                $sent++;
            });

        $this->components->info("{$sent} reminder(s) sent");

        return self::SUCCESS;
    }

    private function broadcastRenewal(MessageNotifier $notifier, Renewal $renewal, int $days): void
    {
        $when = $days < 0
            ? __('EXPIRED :days day(s) ago', ['days' => abs($days)])
            : __('expires in :days day(s)', ['days' => $days]);
        $notifier->broadcast(
            __('[Renewal] :label :when', ['label' => $renewal->label, 'when' => $when]),
            "{$renewal->kind}: {$renewal->label}\n".__('Expires on :date (:when).', ['date' => $renewal->expires_on->toDateString(), 'when' => $when]),
        );
    }
}
