<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nocline:metrics:prune')->dailyAt('03:15');
Schedule::command('nocline:checks:run-due')->everyMinute()->withoutOverlapping();
Schedule::command('nocline:heartbeats:check')->everyMinute()->withoutOverlapping();
Schedule::command('nocline:metrics:rollup')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('nocline:renewals:scan-ssl')->dailyAt('02:30');
Schedule::command('nocline:renewals:scan-domains')->dailyAt('03:00');
Schedule::command('nocline:renewals:remind')->dailyAt('08:00');
Schedule::command('nocline:update:check')->dailyAt('04:10');
Schedule::command('nocline:stack-eol:refresh')->dailyAt('04:20');
Schedule::command('nocline:checks:prune')->dailyAt('03:30');
Schedule::command('nocline:checks:refresh-favicons')->dailyAt('05:20');
Schedule::command('nocline:checks:refresh-frameworks')->weeklyOn(0, '05:40');
Schedule::command('nocline:hosts:refresh-providers')->weeklyOn(0, '06:00');
Schedule::command('nocline:maintenance:catch-up')->everyMinute()->withoutOverlapping();
Schedule::command('nocline:alerts:release-snoozes')->everyMinute()->withoutOverlapping();
Schedule::command('nocline:audit:prune')->dailyAt('03:45');
