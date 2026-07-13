<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ewms:send-due-notifications')->hourly();
Schedule::command('ewms:generate-recurring-tasks')->hourly();

// GA4 BigQuery Export's daily tables finalize a few hours after midnight in
// the property's reporting timezone; running once, after that window, keeps
// this from reading a partial `events_YYYYMMDD` table.
Schedule::command('ewms:sync-ga4-analytics')->dailyAt('04:00');

// GSC data is synced with a lag (see analytics.gsc.sync_lag_days) since
// Search Console figures take a few days to stabilize.
Schedule::command('ewms:sync-gsc-analytics')->dailyAt('05:00');
