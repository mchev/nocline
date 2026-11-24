<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('phare:metrics:prune')->dailyAt('03:15');
Schedule::command('phare:checks:run-due')->everyMinute()->withoutOverlapping();
Schedule::command('phare:heartbeats:check')->everyMinute()->withoutOverlapping();
Schedule::command('phare:metrics:rollup')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('phare:renewals:scan-ssl')->dailyAt('02:30');
Schedule::command('phare:renewals:scan-domains')->dailyAt('03:00');
Schedule::command('phare:renewals:remind')->dailyAt('08:00');
Schedule::command('phare:update:check')->dailyAt('04:10');
Schedule::command('phare:checks:prune')->dailyAt('03:30');
Schedule::command('phare:checks:refresh-favicons')->dailyAt('05:20');
Schedule::command('phare:checks:refresh-frameworks')->weeklyOn(0, '05:40');
Schedule::command('phare:hosts:refresh-providers')->weeklyOn(0, '06:00');
Schedule::command('phare:maintenance:catch-up')->everyMinute()->withoutOverlapping();
Schedule::command('phare:audit:prune')->dailyAt('03:45');
