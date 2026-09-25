<?php

namespace App\Services\Cs;

use App\Jobs\GenerateCsDailyCardReport;
use App\Models\CompanySetting;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\CsTaskTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Seo\WorkCalendarService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The midnight recurring lifecycle for Customer Service daily cards —
 * Customer Service Board Requirements Specification v1.0 §5.3. Runs from a
 * frequently-polling scheduled command (see
 * App\Console\Commands\CloseCsDailyCards) rather than depending on cron
 * firing at exactly 00:00. Mirrors App\Services\Seo\SeoCardLifecycleService;
 * reuses WorkCalendarService as-is since leave/holiday handling isn't
 * SEO-specific.
 */
class CsCardLifecycleService
{
    public function __construct(
        private readonly WorkCalendarService $calendar,
        private readonly CsScoringService $scoring,
    ) {}

    public function timezone(): string
    {
        return CompanySetting::current()->timezone ?: 'Africa/Nairobi';
    }

    public function businessDay(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    /** Closes every daily card whose work_date has passed in the company's configured timezone, then opens the next applicable one. Returns how many were closed. */
    public function closeDueCards(): int
    {
        $today = $this->businessDay()->toDateString();

        $due = CsDailyCard::query()
            ->open()
            ->where('work_date', '<', $today)
            ->get();

        $closed = 0;

        foreach ($due as $card) {
            try {
                DB::transaction(function () use ($card) {
                    $this->closeOne($card);
                });
                $closed++;
            } catch (Throwable $e) {
                // One employee's failure must never block another's close or
                // next-day creation — §11 "delivery failure must not prevent
                // card closure, archiving or next-day card creation" extends
                // to the close step itself.
                Log::error('Customer Service daily card close failed.', [
                    'card_id' => $card->id,
                    'employee_id' => $card->employee_id,
                    'work_date' => $card->work_date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed;
    }

    private function closeOne(CsDailyCard $card): void
    {
        $card = CsDailyCard::query()->lockForUpdate()->findOrFail($card->id);

        if ($card->status === CsDailyCard::STATUS_CLOSED) {
            return; // Already closed by a concurrent run.
        }

        $this->scoring->recalculateEmployeeSubmitted($card);
        $this->scoring->recalculateCard($card);
        $card->refresh();

        $snapshot = [
            'card' => $card->only(['id', 'employee_id', 'department_id', 'work_date', 'planned_points', 'employee_submitted_points', 'approved_points']),
            'items' => $card->items()->get()->map(fn (CsCardItem $i) => $i->only([
                'id', 'section', 'name', 'classification', 'weight', 'target_quantity', 'available_target_quantity',
                'achieved_quantity', 'employee_status', 'employee_comment', 'submitted_at', 'blocker_reason',
                'hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points',
            ]))->all(),
            'closed_at' => now()->toIso8601String(),
        ];

        $card->update([
            'status' => CsDailyCard::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_snapshot' => $snapshot,
        ]);

        $employee = $card->employee;

        // A stray card (someone who isn't a scored CS team member, or who has
        // since transferred or become a manager) must not recreate itself
        // every night or keep emailing their HOD: membership is re-checked
        // here at close time, not trusted from whenever the card was opened.
        $isCurrentlyCsEmployee = $employee?->user?->isCsEmployee() ?? false;

        if ($employee !== null && $isCurrentlyCsEmployee) {
            $this->createNextCard($employee, $employee->user->department_id, $card->work_date->copy());
        }

        if ($isCurrentlyCsEmployee) {
            GenerateCsDailyCardReport::dispatch($card->id);
        } else {
            Log::warning('Skipped Customer Service daily card report and next-day card: owner is not currently a CS team member.', [
                'card_id' => $card->id,
                'employee_id' => $card->employee_id,
                'department_id' => $card->department_id,
            ]);
        }
    }

    /**
     * Provisions today's card for every active Customer Service team member
     * who doesn't have one yet, so the HOD's team view has rows (and the
     * assign controls) before anyone has visited their own board. Idempotent
     * (createNextCard() no-ops on an existing card) and safe to run on every
     * poll of the scheduled close command. Inactive staff, and anyone who is
     * not a scored CS employee (managers, assistants, CEO, other departments),
     * are skipped.
     */
    public function ensureTodaysCardsExist(): int
    {
        $cs = Department::query()->where('slug', 'customer-service')->first();
        if ($cs === null) {
            return 0;
        }

        $today = $this->businessDay();
        $yesterday = $today->copy()->subDay();

        $employees = CsAccess::scoredEmployees($cs);

        $created = 0;

        foreach ($employees as $employee) {
            $hasToday = CsDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $today->toDateString())->exists();
            if ($hasToday) {
                continue;
            }

            if ($this->createNextCard($employee, $employee->user->department_id, $yesterday->copy())->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /** Opens a clean card for the next date $employee is actually expected to work — never the closed card, never copying its status/evidence/comments forward, per §5.3. */
    public function createNextCard(Employee $employee, int $departmentId, Carbon $afterDate): CsDailyCard
    {
        $nextDate = $this->calendar->nextWorkingDay($employee, $afterDate->copy()->addDay());

        // Not firstOrCreate(): its lookup compares the raw date string against
        // work_date's actual stored (full-datetime) format and never matches,
        // which would insert a second card for the same employee/date and trip
        // the unique index — see SeoCardLifecycleService::createNextCard for
        // the identical pitfall.
        $existing = CsDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $nextDate->toDateString())->first();
        $card = $existing ?? CsDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $departmentId,
            'work_date' => $nextDate->toDateString(), 'status' => CsDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);

        if ($existing === null) {
            $this->seedMandatoryItems($card);
        }

        return $card;
    }

    /**
     * Seeds every active daily template onto the new card — unlike the SEO
     * Board, the Customer Service Board's daily responsibilities (§5) are a
     * fixed, fully-specified 100-point list with no separate HOD "assign
     * today's production items" step, so every daily template is mandatory
     * by default and gets seeded in full each morning.
     */
    public function seedMandatoryItems(CsDailyCard $card): void
    {
        $templates = CsTaskTemplate::query()
            ->active()
            ->forCardType(CsTaskTemplate::CARD_TYPE_DAILY)
            ->orderBy('position')
            ->get();

        foreach ($templates as $template) {
            CsCardItem::query()->create([
                'cardable_type' => CsDailyCard::class,
                'cardable_id' => $card->id,
                'template_id' => $template->id,
                'section' => $template->section,
                'name' => $template->name,
                'classification' => $template->classification,
                'weight' => $template->default_weight,
                'quantity_unit' => $template->quantity_unit,
                'completion_criteria' => $template->completion_criteria,
                'evidence_type' => $template->evidence_type,
                'evidence_required' => $template->evidence_required,
                'employee_status' => CsCardItem::STATUS_NOT_STARTED,
                'position' => $template->position,
            ]);
        }
    }
}
