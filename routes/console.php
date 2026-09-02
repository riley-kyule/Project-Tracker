<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ewms:send-due-notifications')->hourly();
Schedule::command('ewms:generate-recurring-tasks')->hourly();
Schedule::command('ewms:check-analytics-freshness')->dailyAt('08:00');
Schedule::command('ewms:warm-analytics-cache')->dailyAt('00:10');
Schedule::command('ewms:send-daily-summaries')->everyFifteenMinutes();
Schedule::command('ewms:send-weekly-summaries')->hourly();
Schedule::command('ewms:run-scheduled-backup')->hourly();
Schedule::command('ewms:reset-recurring-tasks')->hourly();
Schedule::command('ewms:sync-wordpress-users')->dailyAt('01:00');
Schedule::command('ewms:hr-contract-alerts')->dailyAt('07:00');
Schedule::command('ewms:accrue-leave')->monthlyOn(1, '01:00');
