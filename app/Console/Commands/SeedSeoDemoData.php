<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoTaskTemplate;
use App\Models\SeoWeeklyCard;
use App\Models\User;
use App\Services\Seo\SeoCardLifecycleService;
use App\Services\Seo\SeoScoringService;
use App\Services\Seo\WorkCalendarService;
use Illuminate\Console\Command;

/**
 * Fills in realistic SEO Board history for the real SEO team so there's
 * something meaningful to look at before rollout — never for a real
 * production instance, only ever a local/staging copy someone is using to
 * evaluate the feature. Idempotent: safe to re-run, it only ever fills in
 * days that don't already have a card.
 */
class SeedSeoDemoData extends Command
{
    protected $signature = 'ewms:seed-seo-demo-data {--days=10 : How many trailing workdays of daily-card history to create}';

    protected $description = 'Seed realistic SEO Board demo data (daily card history, a current weekly plan, calibration date) for the real SEO team — local/staging use only';

    public function handle(SeoCardLifecycleService $lifecycle, SeoScoringService $scoring, WorkCalendarService $calendar): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run against a production environment. This seeds fake performance data.');

            return self::FAILURE;
        }

        $department = Department::query()->where('slug', 'seo')->first();
        if ($department === null) {
            $this->error('No SEO department found.');

            return self::FAILURE;
        }

        $hod = $department->resolveHod();
        if ($hod === null) {
            $this->warn('No HOD is resolvable for SEO (Department.manager_id unset on SEO or Marketing) — decisions will be recorded without a real actor.');
        }

        $users = User::query()->where('department_id', $department->id)->where('status', User::STATUS_ACTIVE)->get();
        if ($users->isEmpty()) {
            $this->error('No active users are assigned to the SEO department (User::department_id).');

            return self::FAILURE;
        }

        $this->info("Seeding {$this->option('days')} workdays of history for {$users->count()} SEO team member(s)...");

        foreach ($users as $user) {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee === null) {
                $this->warn("Skipping {$user->name} — no linked Employee (HR) record.");

                continue;
            }

            $this->seedDailyHistory($employee, $department, $hod, $lifecycle, $scoring, $calendar, (int) $this->option('days'));
            $this->seedCurrentWeek($employee, $department, $hod);
        }

        $calibrationEndsAt = CompanySetting::current()->seo_calibration_ends_at;
        if ($calibrationEndsAt === null) {
            CompanySetting::current()->update(['seo_calibration_ends_at' => now()->addWeeks(4)->toDateString()]);
            $this->info('Set the calibration end date to 4 weeks from today.');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function seedDailyHistory(Employee $employee, Department $department, ?User $hod, SeoCardLifecycleService $lifecycle, SeoScoringService $scoring, WorkCalendarService $calendar, int $days): void
    {
        $productionTemplates = SeoTaskTemplate::query()->active()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)->get();

        $date = today()->subDay();
        $created = 0;
        $scenario = 0;

        while ($created < $days && $date->isAfter(today()->subMonths(2))) {
            if (! $calendar->isWorkingDay($employee, $date)) {
                $date = $date->copy()->subDay();

                continue;
            }

            $existing = SeoDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $date->toDateString())->first();
            if ($existing !== null) {
                $date = $date->copy()->subDay();
                $created++;

                continue;
            }

            $card = SeoDailyCard::query()->create([
                'employee_id' => $employee->id, 'department_id' => $department->id,
                'work_date' => $date->toDateString(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
            ]);
            $lifecycle->seedMandatoryItems($card);

            // Round-robin a handful of realistic day shapes so history/exceptions/trends have something to show.
            $shape = $scenario % 5;
            $scenario++;

            $picked = $productionTemplates->random(min(2, $productionTemplates->count()));
            $remaining = 65;
            foreach ($picked as $i => $template) {
                $weight = $i === $picked->count() - 1 ? $remaining : min($template->max_weight, (int) ($remaining / 2));
                $weight = max((int) $template->min_weight, $weight);
                $remaining -= $weight;
                $card->items()->create([
                    'template_id' => $template->id, 'section' => $template->section, 'name' => $template->name,
                    'classification' => SeoTaskTemplate::CLASSIFICATION_PRODUCTION, 'weight' => $weight,
                    'target_quantity' => $template->requires_quantity ? 10 : null, 'quantity_unit' => $template->quantity_unit,
                    'completion_criteria' => $template->completion_criteria, 'evidence_type' => $template->evidence_type,
                    'evidence_required' => $template->evidence_required, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 90 + $i,
                ]);
            }

            $items = $card->items()->get();
            foreach ($items as $index => $item) {
                $item->update([
                    'employee_status' => SeoCardItem::STATUS_SUBMITTED,
                    'submitted_at' => $date->copy()->setTime(16, 0),
                    'employee_comment' => 'Completed as assigned.',
                    'achieved_quantity' => $item->target_quantity,
                ]);

                $decision = match (true) {
                    $shape === 0 => SeoCardItem::DECISION_APPROVED,
                    $shape === 1 && $index === 0 => SeoCardItem::DECISION_MINOR_CORRECTION,
                    $shape === 1 => SeoCardItem::DECISION_APPROVED,
                    $shape === 2 && $index === 0 => SeoCardItem::DECISION_REJECTED,
                    $shape === 2 => SeoCardItem::DECISION_APPROVED,
                    $shape === 3 => SeoCardItem::DECISION_APPROVED_LATE,
                    default => null, // shape 4: left pending, to populate "awaiting review" / missing-evidence-style backlog
                };

                if ($shape === 4 && $index === 0) {
                    $item->update(['employee_status' => SeoCardItem::STATUS_BLOCKED, 'blocker_reason' => 'Waiting on client CMS access.', 'escalated_at' => now()]);
                    if ($hod !== null) {
                        $scoring->decide($item, SeoCardItem::DECISION_EXCLUDED, 'Valid blocker, outside the employee\'s control.', $hod);
                    }

                    continue;
                }

                if ($decision !== null && $hod !== null) {
                    $scoring->decide($item, $decision, $decision === SeoCardItem::DECISION_APPROVED ? null : 'Demo data.', $hod);
                }
            }

            $scoring->recalculateEmployeeSubmitted($card);

            if ($shape !== 4) {
                $card->update(['status' => SeoDailyCard::STATUS_CLOSED, 'closed_at' => $date->copy()->setTime(0, 5)]);
            }

            $date = $date->copy()->subDay();
            $created++;
        }
    }

    private function seedCurrentWeek(Employee $employee, Department $department, ?User $hod): void
    {
        $weekStart = today()->startOfWeek();
        $existing = SeoWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $weekStart->toDateString())->first();
        if ($existing !== null) {
            return;
        }

        $card = SeoWeeklyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'week_start_date' => $weekStart->toDateString(), 'week_end_date' => $weekStart->copy()->endOfWeek()->toDateString(),
            'status' => SeoWeeklyCard::STATUS_DRAFT, 'planned_points' => 100,
        ]);

        foreach (SeoTaskTemplate::query()->active()->forCardType(SeoTaskTemplate::CARD_TYPE_WEEKLY)->orderBy('position')->get() as $template) {
            $card->items()->create([
                'template_id' => $template->id, 'section' => $template->section, 'name' => $template->name,
                'classification' => $template->classification, 'weight' => $template->default_weight,
                'target_quantity' => $template->requires_quantity ? 5 : null, 'quantity_unit' => $template->quantity_unit,
                'completion_criteria' => $template->completion_criteria, 'evidence_type' => $template->evidence_type,
                'evidence_required' => $template->evidence_required, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => $template->position,
            ]);
        }

        if ($hod !== null) {
            $card->update(['status' => SeoWeeklyCard::STATUS_PLAN_APPROVED, 'plan_approved_by' => $hod->id, 'plan_approved_at' => now()]);
        }
    }
}
