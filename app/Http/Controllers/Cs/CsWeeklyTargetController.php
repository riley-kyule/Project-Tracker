<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsSalesAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * HOD-set market-adjusted weekly commercial targets — Customer Service
 * Board Requirements Specification v1.0 §3. Employees can never reach this
 * controller (gated by cs.cards.approve), satisfying "employees cannot
 * change their own quotas".
 */
class CsWeeklyTargetController extends Controller
{
    public function store(Request $request, Employee $employee, CsSalesAttributionService $attribution): RedirectResponse
    {
        abort_unless(CsAccess::leadsEmployee($request->user(), $employee), 403);

        $validated = $request->validate([
            'week_start_date' => ['required', 'date'],
            'new_customers_target' => ['required', 'integer', 'min:0'],
            'new_customer_revenue_target' => ['required', 'numeric', 'min:0'],
            'renewed_customers_target' => ['required', 'integer', 'min:0'],
            'retained_revenue_target' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $attribution->setWeeklyTarget(
                $employee,
                Carbon::parse($validated['week_start_date']),
                $validated,
                $request->user(),
                $validated['reason'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Weekly targets saved.');
    }
}
