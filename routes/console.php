<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ewms:send-due-notifications')->hourly();
Schedule::command('ewms:generate-recurring-tasks')->hourly();

// GA4 BigQuery Export's daily tables finalize a few hours after midnight in
// the property's reporting timezone; running once, after that window, keeps
// this from reading a partial `events_YYYYMMDD` table.
Schedule::command('ewms:sync-ga4-analytics')->dailyAt('04:00');
