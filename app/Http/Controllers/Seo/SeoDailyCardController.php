<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoTaskTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * SEO Board Requirements Specification v1.1 §4 — daily card actions. Viewing
 * happens inline on the "My SEO Board" (SeoBoardController) and "SEO Board
 * (HOD)" (SeoHodController) hubs; this controller is action-only.
 */
class SeoDailyCardController extends Controller
{
    /** HOD assigns the day's production items (§4.4/§9 workflow rule 2) — replaces any not-yet-decided production items with the given selection so the card totals exactly 100. */
    public function assignItems(Request $request, SeoDailyCard $card): RedirectResponse
    {
        Gate::authorize('approve', $card);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.template_id' => ['required', 'integer', 'exists:seo_task_templates,id'],
            'items.*.weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'items.*.target_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'items.*.assigned_url' => ['nullable', 'string', 'max:2048'],
            'items.*.due_time' => ['nullable', 'date_format:H:i'],
        ]);

        // §8 "Weight ... card total must equal 100" / §13 "Card totals" acceptance
        // test — the weekly card enforces this at plan-approval time; the daily
        // card has no separate approval step, so it must be enforced right here,
        // before the new selection is ever saved.
        $templatesById = SeoTaskTemplate::query()->findMany(collect($validated['items'])->pluck('template_id'))->keyBy('id');

        foreach ($validated['items'] as $row) {
            $template = $templatesById->get($row['template_id']);
            if ($template !== null && ((float) $row['weight'] < (float) $template->min_weight || (float) $row['weight'] > (float) $template->max_weight)) {
                throw ValidationException::withMessages([
                    'items' => "\"{$template->name}\" must be weighted between {$template->min_weight} and {$template->max_weight} points.",
                ]);
            }
        }

        $fixedWeight = (float) $card->items()
            ->where(function ($q) {
                $q->where('classification', '!=', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)
                    ->orWhereNotNull('hod_decision');
            })
            ->sum('weight');
        $newProductionWeight = collect($validated['items'])->sum(fn (array $row) => (float) $row['weight']);

        if (abs($fixedWeight + $newProductionWeight - 100.0) >= 0.01) {
            throw ValidationException::withMessages([
                'items' => "This assignment must bring the card to exactly 100 points — it currently totals {$fixedWeight} fixed + {$newProductionWeight} assigned = ".($fixedWeight + $newProductionWeight).'.',
            ]);
        }

        DB::transaction(function () use ($card, $validated) {
            $card->items()
                ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)
                ->whereNull('hod_decision')
                ->delete();

            $position = (int) $card->items()->max('position') + 1;

            foreach ($validated['items'] as $row) {
                $template = SeoTaskTemplate::query()->findOrFail($row['template_id']);

                $card->items()->create([
                    'template_id' => $template->id,
                    'section' => $template->section,
                    'name' => $template->name,
                    'classification' => SeoTaskTemplate::CLASSIFICATION_PRODUCTION,
                    'weight' => $row['weight'],
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

        AuditLogger::log($card, 'seo_daily_card.items_assigned', [], ['item_count' => count($validated['items'])]);

        return back()->with('success', 'Today\'s production items are assigned.');
    }

    /** HOD-authorised reopen of a closed card — §4.2 "After midnight" row. */
    public function reopen(Request $request, SeoDailyCard $card): RedirectResponse
    {
        Gate::authorize('reopen', $card);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $old = $card->only(['status']);
        $card->update([
            'status' => SeoDailyCard::STATUS_REOPENED,
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'reopen_reason' => $validated['reason'],
        ]);

        AuditLogger::log($card, 'seo_daily_card.reopened', $old, ['status' => $card->status, 'reason' => $validated['reason']]);

        return back()->with('success', 'Card reopened.');
    }
}
