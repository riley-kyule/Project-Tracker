<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoTaskTemplate;
use App\Models\SeoWeeklyCard;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * SEO Board Requirements Specification v1.1 §5 — weekly card actions.
 * Viewing happens inline on the "My SEO Board" and "SEO Board (HOD)" hubs;
 * this controller is action-only.
 */
class SeoWeeklyCardController extends Controller
{
    /** HOD builds/edits the week's plan — must be approved before the week begins, per §5/§9 workflow rule 2. */
    public function assignItems(Request $request, Employee $employee, string $weekStart): RedirectResponse
    {
        $start = Carbon::parse($weekStart)->startOfWeek();

        // Not firstOrCreate(): its lookup compares the raw search string against
        // week_start_date's actual stored (full-datetime) format and never matches,
        // which would attempt a duplicate insert on a second call for the same week.
        // User::department_id, not Employee::department_id — see the note in
        // SeoHodPanelData::forDepartment(); the HR field can legitimately point
        // at a parent department while the person's actual SEO Board team is
        // the sub-department their platform login belongs to.
        $card = SeoWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $start->toDateString())->first()
            ?? SeoWeeklyCard::query()->create([
                'employee_id' => $employee->id, 'department_id' => $employee->user?->department_id ?? $employee->department_id,
                'week_start_date' => $start->toDateString(), 'week_end_date' => $start->copy()->endOfWeek()->toDateString(),
                'status' => SeoWeeklyCard::STATUS_DRAFT, 'planned_points' => 100,
            ]);

        Gate::authorize('approve', $card);

        if ($card->isPlanApproved()) {
            throw ValidationException::withMessages(['items' => 'The weekly plan is already approved — use an in-period adjustment with a reason instead of reassigning.']);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.template_id' => ['required', 'integer', 'exists:seo_task_templates,id'],
            'items.*.target_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'items.*.assigned_url' => ['nullable', 'string', 'max:2048'],
            'items.*.due_time' => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($card, $validated) {
            $card->items()->delete();

            $position = 1;
            foreach ($validated['items'] as $row) {
                $template = SeoTaskTemplate::query()->findOrFail($row['template_id']);

                $card->items()->create([
                    'template_id' => $template->id,
                    'section' => $template->section,
                    'name' => $template->name,
                    'classification' => $template->classification,
                    'weight' => $template->default_weight,
                    'target_quantity' => $row['target_quantity'] ?? null,
                    'quantity_unit' => $row['quantity_unit'] ?? $template->quantity_unit,
                    'assigned_url' => $row['assigned_url'] ?? null,
                    'due_time' => $row['due_time'] ?? null,
                    'completion_criteria' => $template->completion_criteria,
                    'evidence_type' => $template->evidence_type,
                    'evidence_required' => $template->evidence_required,
                    'employee_status' => SeoCardItem::STATUS_NOT_STARTED,
                    'position' => $position++,
                ]);
            }
        });

        AuditLogger::log($card, 'seo_weekly_card.items_assigned', [], ['item_count' => count($validated['items'])]);

        return back()->with('success', 'Weekly plan saved.');
    }

    public function approvePlan(Request $request, SeoWeeklyCard $card): RedirectResponse
    {
        Gate::authorize('approve', $card);

        $totalWeight = (float) $card->items()->sum('weight');
        if (abs($totalWeight - 100.0) >= 0.01) {
            throw ValidationException::withMessages(['items' => "The weekly plan must total exactly 100 points — it currently totals {$totalWeight}."]);
        }

        $card->update(['status' => SeoWeeklyCard::STATUS_PLAN_APPROVED, 'plan_approved_by' => $request->user()->id, 'plan_approved_at' => now()]);

        AuditLogger::log($card, 'seo_weekly_card.plan_approved', [], ['plan_approved_at' => $card->plan_approved_at]);

        return back()->with('success', 'Weekly plan approved.');
    }

    public function reopen(Request $request, SeoWeeklyCard $card): RedirectResponse
    {
        Gate::authorize('reopen', $card);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $old = $card->only(['status']);
        $card->update([
            'status' => SeoWeeklyCard::STATUS_REOPENED,
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'reopen_reason' => $validated['reason'],
        ]);

        AuditLogger::log($card, 'seo_weekly_card.reopened', $old, ['status' => $card->status, 'reason' => $validated['reason']]);

        return back()->with('success', 'Card reopened.');
    }
}
