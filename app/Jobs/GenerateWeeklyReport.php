<?php

namespace App\Jobs;

use App\Mail\WeeklyPersonalSummaryMail;
use App\Models\CompanySetting;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\Reports\WeeklyPersonalSummaryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** One job per employee's weekly summary — mirrors GenerateDailyReport's shape and idempotency guard. */
class GenerateWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $weekEndDay,
    ) {}

    public function handle(WeeklyPersonalSummaryBuilder $builder): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $timezone = CompanySetting::current()->timezone ?: 'Africa/Nairobi';
        $weekEndDay = Carbon::parse($this->weekEndDay, $timezone);

        $payload = $builder->build($user, $weekEndDay, $timezone);

        $snapshot = $this->recordSnapshot($user, $payload);

        if ($snapshot === null) {
            // Unique constraint hit: another run already generated this user's report for this week.
            return;
        }

        $mail = new WeeklyPersonalSummaryMail($user, $payload['completed_count'], $payload['completed'], $payload['pending_breakdown']);

        $delivery = ReportDelivery::query()->create([
            'report_snapshot_id' => $snapshot->id,
            'recipient_user_id' => $user->id,
            'status' => ReportDelivery::STATUS_PENDING,
        ]);

        try {
            Mail::to($user)->send($mail);

            $delivery->update(['status' => ReportDelivery::STATUS_SENT, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $delivery->update([
                'status' => ReportDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => Str::limit($e->getMessage(), 500),
                'retry_count' => $delivery->retry_count + 1,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateWeeklyReport job failed.', [
            'user_id' => $this->userId,
            'week_end_day' => $this->weekEndDay,
            'error' => $exception->getMessage(),
        ]);
    }

    private function recordSnapshot(User $user, array $payload): ?ReportSnapshot
    {
        try {
            return ReportSnapshot::query()->create([
                'report_date' => $this->weekEndDay,
                'report_type' => ReportSnapshot::TYPE_WEEKLY_PERSONAL,
                'department_id' => null,
                'user_id' => $user->id,
                'generated_at' => now(),
                'payload' => json_decode(json_encode($payload), true),
                'status' => ReportSnapshot::STATUS_GENERATED,
                'version' => 1,
            ]);
        } catch (QueryException $e) {
            $isUniqueViolation = $e->getCode() === '23505' || str_contains($e->getMessage(), 'UNIQUE constraint failed');

            if (! $isUniqueViolation) {
                throw $e;
            }

            Log::info('Weekly report already generated for this user, skipping duplicate.', [
                'user_id' => $user->id,
                'week_end_day' => $this->weekEndDay,
            ]);

            return null;
        }
    }
}
