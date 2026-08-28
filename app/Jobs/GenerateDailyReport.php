<?php

namespace App\Jobs;

use App\Mail\CeoDailySummaryMail;
use App\Mail\DepartmentDailySummaryMail;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\Reports\CeoSummaryBuilder;
use App\Services\Reports\DepartmentSummaryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * One job per report (one CEO summary, or one department summary) so a
 * failure generating or sending one doesn't block any other — replaces the
 * previous single-command-does-everything approach in SendDailySummaries.
 */
class GenerateDailyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The worker's own --tries flag (docs/DEPLOYMENT.md) already covers
    // retries; this only bounds a single attempt so one slow aggregate
    // query or a hung mail send can't tie up a worker indefinitely.
    public int $timeout = 120;

    public function __construct(
        public string $reportType,
        public ?int $departmentId,
        public string $businessDay,
    ) {}

    public function handle(CeoSummaryBuilder $ceoBuilder, DepartmentSummaryBuilder $departmentBuilder): void
    {
        $companySetting = CompanySetting::current();
        $timezone = $companySetting->timezone ?: 'Africa/Nairobi';
        $businessDay = Carbon::parse($this->businessDay, $timezone);

        if ($this->reportType === ReportSnapshot::TYPE_CEO_DAILY) {
            $recipients = User::role('CEO')->get();
            $built = $ceoBuilder->build($businessDay, $timezone, $companySetting->ceo_summary_time);
            $payload = $built;
            $mail = new CeoDailySummaryMail($built['rows'], $built['totalCompletedToday'], $built['totalPending']);
        } else {
            $department = Department::findOrFail($this->departmentId);
            $recipients = collect([$department->manager, $department->assistantManager])->filter()->unique('id');
            $payload = $departmentBuilder->build($department, $businessDay, $timezone, $department->daily_summary_time);
            $mail = new DepartmentDailySummaryMail(
                $department,
                $payload['completed_today'],
                $payload['pending'],
                $payload['breakdown'],
                $payload['comments'],
                $payload['pending_breakdown'],
                $payload['progress_notes'],
                $payload['reopened_today'],
                $payload['completeness'],
            );
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $snapshot = $this->recordSnapshot($payload);

        if ($snapshot === null) {
            // Unique constraint hit: another run already generated this report for today.
            return;
        }

        $this->deliver($snapshot, $recipients, $mail);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateDailyReport job failed.', [
            'report_type' => $this->reportType,
            'department_id' => $this->departmentId,
            'business_day' => $this->businessDay,
            'error' => $exception->getMessage(),
        ]);
    }

    private function recordSnapshot(array $payload): ?ReportSnapshot
    {
        try {
            // json_decode(json_encode()) flattens the Collections inside $payload
            // (breakdown/comments/rows) into plain arrays for JSON storage, without
            // needing every builder to also produce a parallel plain-array shape.
            return ReportSnapshot::query()->create([
                'report_date' => $this->businessDay,
                'report_type' => $this->reportType,
                'department_id' => $this->departmentId,
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

            Log::info('Daily report already generated for today, skipping duplicate.', [
                'report_type' => $this->reportType,
                'department_id' => $this->departmentId,
                'business_day' => $this->businessDay,
            ]);

            return null;
        }
    }

    /** @param  Collection<int, User>  $recipients */
    private function deliver(ReportSnapshot $snapshot, Collection $recipients, Mailable $mail): void
    {
        foreach ($recipients as $recipient) {
            $delivery = ReportDelivery::query()->create([
                'report_snapshot_id' => $snapshot->id,
                'recipient_user_id' => $recipient->id,
                'status' => ReportDelivery::STATUS_PENDING,
            ]);

            try {
                Mail::to($recipient)->send($mail);

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
    }
}
