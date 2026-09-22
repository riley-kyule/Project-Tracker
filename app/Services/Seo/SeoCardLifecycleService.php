<?php

namespace App\Services\Seo;

use App\Jobs\GenerateSeoDailyCardReport;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoTaskTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The midnight recurring lifecycle for SEO daily cards — SEO Board
 * Requirements Specification v1.1 §4.2. Runs from a frequently-polling
 * scheduled command (see App\Console\Commands\CloseSeoDailyCards) rather than
 * depending on cron firing at exactly 00:00, mirroring
 * App\Services\Reports\DailySummarySchedule's due-time-vs-cron-granularity
 * pattern.
 */
class SeoCardLifecycleService
{
    public function __construct(
        private readonly WorkCalendarService $calendar,
        private readonly SeoScoringService $scoring,
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

        $due = SeoDailyCard::query()
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
                // One employee's failure must never block another's close or next-day
                // creation — SEO Board Spec §4.2.1 "a delivery failure must not
                // prevent the card snapshot or next-day card creation" extends to
                // the close step itself.
                Log::error('SEO daily card close failed.', [
                    'card_id' => $card->id,
                    'employee_id' => $card->employee_id,
                    'work_date' => $card->work_date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed;
    }

    private function closeOne(SeoDailyCard $card): void
    {
        $card = SeoDailyCard::query()->lockForUpdate()->findOrFail($card->id);

        if ($card->status === SeoDailyCard::STATUS_CLOSED) {
            return; // Already closed by a concurrent run.
        }

        $this->scoring->recalculateEmployeeSubmitted($card);
        $this->scoring->recalculateCard($card);
        $card->refresh();

        $snapshot = [
            'card' => $card->only(['id', 'employee_id', 'department_id', 'work_date', 'planned_points', 'employee_submitted_points', 'approved_points']),
            'items' => $card->items()->get()->map(fn (SeoCardItem $i) => $i->only([
                'id', 'section', 'name', 'classification', 'weight', 'target_quantity', 'available_target_quantity',
                'achieved_quantity', 'employee_status', 'employee_comment', 'submitted_at', 'blocker_reason',
                'hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points',
            ]))->all(),
            'closed_at' => now()->toIso8601String(),
        ];

        $card->update([
            'status' => SeoDailyCard::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_snapshot' => $snapshot,
        ]);

        $employee = $card->employee;

        if ($employee !== null) {
            $this->createNextCard($employee, $card->department_id, $card->work_date->copy());
        }

        GenerateSeoDailyCardReport::dispatch($card->id);
    }

    /** Opens a clean card for the next date $employee is actually expected to work — never the closed card, never copying its status/evidence/comments forward, per §4.2/§9.2. */
    public function createNextCard(Employee $employee, int $departmentId, Carbon $afterDate): SeoDailyCard
    {
        $nextDate = $this->calendar->nextWorkingDay($employee, $afterDate->copy()->addDay());

        // Not firstOrCreate(): its lookup compares the raw date string against
        // work_date's actual stored (full-datetime) format and never matches,
        // which would insert a second card for the same employee/date and trip
        // the unique index — see SeoScoringService::recalculateFinalScore for
        // the same pitfall spelled out in full.
        $existing = SeoDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $nextDate->toDateString())->first();
        $card = $existing ?? SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $departmentId,
            'work_date' => $nextDate->toDateString(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);

        if ($existing === null) {
            $this->seedMandatoryItems($card);
        }

        return $card;
    }

    /**
     * Seeds only the fixed-weight "mandatory" sections (monitoring,
     * implementation, documentation — 35 of the 100 points, per §4.1). The
     * remaining 65 "assigned production work" points are left for the HOD to
     * allocate before work begins, per §4.4/§9 workflow rule 2 — they are
     * never copied forward from the prior day's card.
     */
    public function seedMandatoryItems(SeoDailyCard $card): void
    {
        $templates = SeoTaskTemplate::query()
            ->active()
            ->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_MANDATORY)
            ->orderBy('position')
            ->get();

        foreach ($templates as $template) {
            SeoCardItem::query()->create([
                'cardable_type' => SeoDailyCard::class,
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
                'employee_status' => SeoCardItem::STATUS_NOT_STARTED,
                'position' => $template->position,
            ]);
        }
    }
}
