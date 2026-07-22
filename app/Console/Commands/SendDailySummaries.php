<?php

namespace App\Console\Commands;

use App\Mail\CeoDailySummaryMail;
use App\Mail\DepartmentDailySummaryMail;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailySummaries extends Command
{
    protected $signature = 'ewms:send-daily-summaries';

    protected $description = 'Email the CEO a company-wide daily summary and each department head their department\'s summary, at their configured times';

    /** Matches the 15-minute schedule this command runs on. */
    private const BUCKET_MINUTES = 15;

    public function handle(): int
    {
        $sentCeo = $this->sendCeoSummary();
        $sentDepartments = $this->sendDepartmentSummaries();

        $this->info("Sent {$sentCeo} CEO summary and {$sentDepartments} department summary email(s).");

        return self::SUCCESS;
    }

    private function sendCeoSummary(): int
    {
        $setting = CompanySetting::current();

        if (! $this->isDue($setting->ceo_summary_time, $setting->ceo_summary_last_sent_on)) {
            return 0;
        }

        $departments = Department::query()->active()->orderBy('name')->get();

        $rows = $departments->map(fn (Department $department) => [
            'name' => $department->name,
            'completed_today' => $this->completedTodayCount($department->id),
            'pending' => $this->pendingCount($department->id),
        ]);

        $ceos = User::role('CEO')->get();

        if ($ceos->isEmpty()) {
            return 0;
        }

        $mail = new CeoDailySummaryMail($rows, (int) $rows->sum('completed_today'), (int) $rows->sum('pending'));
        $ceos->each(fn (User $ceo) => Mail::to($ceo)->queue($mail));

        $setting->update(['ceo_summary_last_sent_on' => now()->toDateString()]);

        return 1;
    }

    private function sendDepartmentSummaries(): int
    {
        $sent = 0;

        Department::query()
            ->active()
            ->whereNotNull('daily_summary_time')
            ->with(['manager', 'assistantManager'])
            ->each(function (Department $department) use (&$sent) {
                if (! $this->isDue($department->daily_summary_time, $department->daily_summary_last_sent_on)) {
                    return;
                }

                $recipients = collect([$department->manager, $department->assistantManager])->filter()->unique('id');

                if ($recipients->isEmpty()) {
                    return;
                }

                $mail = new DepartmentDailySummaryMail(
                    $department,
                    $this->completedTodayCount($department->id),
                    $this->pendingCount($department->id),
                );
                $recipients->each(fn (User $head) => Mail::to($head)->queue($mail));

                $department->update(['daily_summary_last_sent_on' => now()->toDateString()]);
                $sent++;
            });

        return $sent;
    }

    private function completedTodayCount(int $departmentId): int
    {
        return Task::query()->where('department_id', $departmentId)->whereDate('completed_at', today())->count();
    }

    private function pendingCount(int $departmentId): int
    {
        return Task::query()->where('department_id', $departmentId)->whereNull('completed_at')->whereNull('archived_at')->count();
    }

    /** True once per day, the first time `now()` falls in the same 15-minute bucket as $time. */
    private function isDue(?string $time, mixed $lastSentOn): bool
    {
        if ($time === null) {
            return false;
        }

        if ($lastSentOn && now()->isSameDay($lastSentOn)) {
            return false;
        }

        $configured = now()->createFromTimeString($time);
        $bucketStart = $configured->copy()->subMinutes($configured->minute % self::BUCKET_MINUTES);
        $bucketEnd = $bucketStart->copy()->addMinutes(self::BUCKET_MINUTES);

        return now()->between($bucketStart, $bucketEnd);
    }
}
