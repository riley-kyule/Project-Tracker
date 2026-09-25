<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsDailyCard;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Customer Service Board Requirements Specification v1.0 §5 — daily card
 * actions. Unlike the SEO Board's daily card, every Customer Service daily
 * template is seeded automatically each morning (§5's responsibilities are
 * a fixed, fully-specified 100-point list, not variable per-day production
 * work), so there is no "assign items" step here for the HOD to perform:
 * reopen is the only action needed. Viewing happens inline on the "My
 * Customer Service Board" (CsBoardController) hub.
 */
class CsDailyCardController extends Controller
{
    /** HOD-authorised reopen of a closed card — §5.3 "After close" row. */
    public function reopen(Request $request, CsDailyCard $card): RedirectResponse
    {
        Gate::authorize('reopen', $card);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $old = $card->only(['status']);
        $card->update([
            'status' => CsDailyCard::STATUS_REOPENED,
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'reopen_reason' => $validated['reason'],
        ]);

        AuditLogger::log($card, 'cs_daily_card.reopened', $old, ['status' => $card->status, 'reason' => $validated['reason']]);

        return back()->with('success', 'Card reopened.');
    }
}
