<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsCardItem;
use App\Models\CsTaskTemplate;
use App\Models\CsWeeklyCard;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\Cs\CsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Customer Service Board Requirements Specification v1.0 §6 — weekly card
 * actions. Mirrors App\Http\Controllers\Seo\SeoWeeklyCardController.
 */
class CsWeeklyCardController extends Controller
{
    /** HOD builds/edits the week's plan — must be approved before the week begins. */
    public function assignItems(Request $request, Employee $employee, string $weekStart): RedirectResponse
    {
        // Checked before the card is created: an employee who isn't a scored
        // CS team member (or that this user doesn't lead) never gets one.
        abort_unless(CsAccess::leadsEmployee($request->user(), $employee), 403);

        $start = Carbon::parse($weekStart)->startOfWeek();

        $card = CsWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $start->toDateString())->first()
            ?? CsWeeklyCard::query()->create([
                'employee_id' => $employee->id, 'department_id' => $employee->user->department_id,
                'week_start_date' => $start->toDateString(), 'week_end_date' => $start->copy()->endOfWeek()->toDateString(),
                'status' => CsWeeklyCard::STATUS_DRAFT, 'planned_points' => 100,
            ]);

        Gate::authorize('approve', $card);

        if ($card->isPlanApproved()) {
            throw ValidationException::withMessages(['items' => 'The weekly plan is already approved — use an in-period adjustment with a reason instead of reassigning.']);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.template_id' => ['required', 'integer', 'exists:cs_task_templates,id'],
            'items.*.target_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'items.*.due_time' => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($card, $validated) {
            $card->items()->delete();

            $position = 1;
            foreach ($validated['items'] as $row) {
                $template = CsTaskTemplate::query()->findOrFail($row['template_id']);

                $card->items()->create([
                    'template_id' => $template->id,
                    'section' => $template->section,
                    'name' => $template->name,
                    'classification' => $template->classification,
                    'weight' => $template->default_weight,
                    'metric_type' => $template->metric_type,
                    'target_quantity' => $row['target_quantity'] ?? null,
                    'quantity_unit' => $row['quantity_unit'] ?? $template->quantity_unit,
                    'due_time' => $row['due_time'] ?? null,
                    'completion_criteria' => $template->completion_criteria,
                    'evidence_type' => $template->evidence_type,
                    'evidence_required' => $template->evidence_required,
                    'employee_status' => CsCardItem::STATUS_NOT_STARTED,
                    'position' => $position++,
                ]);
            }
        });

        AuditLogger::log($card, 'cs_weekly_card.items_assigned', [], ['item_count' => count($validated['items'])]);

        return back()->with('success', 'Weekly plan saved.');
    }

    public function approvePlan(Request $request, CsWeeklyCard $card): RedirectResponse
    {
        Gate::authorize('approve', $card);

        $totalWeight = (float) $card->items()->sum('weight');
        if (abs($totalWeight - 100.0) >= 0.01) {
            throw ValidationException::withMessages(['items' => "The weekly plan must total exactly 100 points — it currently totals {$totalWeight}."]);
        }

        $card->update(['status' => CsWeeklyCard::STATUS_PLAN_APPROVED, 'plan_approved_by' => $request->user()->id, 'plan_approved_at' => now()]);

        AuditLogger::log($card, 'cs_weekly_card.plan_approved', [], ['plan_approved_at' => $card->plan_approved_at]);

        return back()->with('success', 'Weekly plan approved.');
    }

    public function reopen(Request $request, CsWeeklyCard $card): RedirectResponse
    {
        Gate::authorize('reopen', $card);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $old = $card->only(['status']);
        $card->update([
            'status' => CsWeeklyCard::STATUS_REOPENED,
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'reopen_reason' => $validated['reason'],
        ]);

        AuditLogger::log($card, 'cs_weekly_card.reopened', $old, ['status' => $card->status, 'reason' => $validated['reason']]);

        return back()->with('success', 'Card reopened.');
    }
}
