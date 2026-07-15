<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AnalyticsSourceStale;
use App\Services\Analytics\AnalyticsFreshnessChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAnalyticsFreshness extends Command
{
    protected $signature = 'ewms:check-analytics-freshness';

    protected $description = 'Alert marketing-statistics viewers when GA4, GSC, or Ahrefs data goes stale, missing, or fails';

    /** Matches SendDueNotifications' dedup window — roughly once a day per stale source. */
    private const DEDUP_HOURS = 22;

    private const ALERT_STATUSES = ['delayed', 'missing', 'failed'];

    public function handle(AnalyticsFreshnessChecker $checker): int
    {
        $sources = $checker->check();
        $recipients = User::permission('view marketing statistics')->get();
        $sent = 0;

        foreach ($sources as $source => $result) {
            if (! in_array($result['status'], self::ALERT_STATUSES, true)) {
                continue;
            }

            foreach ($recipients as $recipient) {
                if ($this->alreadySent($recipient->id, $source) || ! $recipient->wantsNotification('analytics_source_stale')) {
                    continue;
                }

                $recipient->notify(new AnalyticsSourceStale($source, $result['status'], $result['error']));
                $sent++;
            }
        }

        $this->info("Sent {$sent} analytics freshness alert(s).");

        return self::SUCCESS;
    }

    private function alreadySent(int $userId, string $source): bool
    {
        return DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('created_at', '>=', now()->subHours(self::DEDUP_HOURS))
            ->where('data->type', 'analytics_source_stale')
            ->where('data->source', $source)
            ->exists();
    }
}
