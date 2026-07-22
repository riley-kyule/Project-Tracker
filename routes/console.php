<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ewms:send-due-notifications')->hourly();
Schedule::command('ewms:generate-recurring-tasks')->hourly();
Schedule::command('ewms:check-analytics-freshness')->dailyAt('08:00');
Schedule::command('ewms:send-daily-summaries')->everyFifteenMinutes();
