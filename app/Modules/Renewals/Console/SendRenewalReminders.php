<?php

namespace App\Modules\Renewals\Console;

use App\Modules\Alertes\Services\MessageNotifier;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Renewals\Models\RenewalReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('phare:renewals:remind')]
#[Description('Send one reminder per crossed threshold (30/14/7/1 days by default) for upcoming expirations')]
class SendRenewalReminders extends Command
{
    public function handle(MessageNotifier $notifier): int
    {
        $sent = 0;

        Renewal::query()
            ->where('expires_on', '<=', now()->addDays(max(Renewal::DEFAULT_REMIND_DAYS) + 60))
            ->each(function (Renewal $renewal) use ($notifier, &$sent): void {
                $days = $renewal->daysRemaining();
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

                $when = $days < 0
                    ? __('EXPIRED :days day(s) ago', ['days' => abs($days)])
                    : __('expires in :days day(s)', ['days' => $days]);
                $notifier->broadcast(
                    __('[Renewal] :label :when', ['label' => $renewal->label, 'when' => $when]),
                    "{$renewal->kind}: {$renewal->label}\n".__('Expires on :date (:when).', ['date' => $renewal->expires_on->toDateString(), 'when' => $when]),
                );
                $sent++;
            });

        $this->components->info("{$sent} reminder(s) sent");

        return self::SUCCESS;
    }
}
