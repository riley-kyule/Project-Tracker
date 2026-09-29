<?php

namespace App\Services\Cs;

use App\Models\CsSalesRecord;
use Illuminate\Support\Carbon;

/**
 * The one place that turns cleared cs_sales_records into an employee's
 * actual counted customers and revenue for a week — used by
 * CsScoringService (the weekly item's achievement), CsPerformanceQuery (the
 * dashboards, history and email), and the MCP tool, so all four can never
 * disagree.
 *
 * Customer Service Board Requirements Specification v1.0 §4 "Shared sale":
 * "the HOD records an approved split or one primary owner. EWMS must not
 * count the same revenue twice." A shared sale's revenue is split between
 * the primary and secondary employee by split_percentage (the percentage
 * attributed to the secondary employee; the primary keeps the remainder),
 * so the two shares always sum to exactly the cleared amount. Customer-count
 * credit stays with the primary owner only — a "unique customer" is a
 * single, whole count per §4's "counted once" rule, so it is never split
 * into fractions the way revenue is.
 */
class CsCommercialTotals
{
    /**
     * @param  list<string>  $categories
     * @return array{customers: int, revenue: float, additional_customers: int, additional_revenue: float}
     */
    public function forEmployee(int $employeeId, Carbon $weekStart, array $categories, int $customerTarget, float $revenueTarget): array
    {
        $weekStartDate = $weekStart->copy()->startOfWeek()->toDateString();

        $owned = CsSalesRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('week_start_date', $weekStartDate)
            ->whereIn('category', $categories)
            ->cleared()
            ->get();

        $sharedIn = CsSalesRecord::query()
            ->where('shared_with_employee_id', $employeeId)
            ->where('attribution_type', CsSalesRecord::ATTRIBUTION_SHARED)
            ->whereDate('week_start_date', $weekStartDate)
            ->whereIn('category', $categories)
            ->cleared()
            ->get();

        $customers = $owned->pluck('customer_identifier')->unique()->count();

        $revenue = $owned->sum(function (CsSalesRecord $r) {
            $amount = (float) ($r->reporting_currency_amount ?? $r->amount);

            if ($r->attribution_type === CsSalesRecord::ATTRIBUTION_SHARED && $r->split_percentage !== null) {
                // The primary keeps the remainder after the secondary's share.
                return $amount * (100 - (float) $r->split_percentage) / 100;
            }

            return $amount;
        });

        $revenue += $sharedIn->sum(fn (CsSalesRecord $r) => (float) ($r->reporting_currency_amount ?? $r->amount) * (float) $r->split_percentage / 100);

        // §3 "Additional sales above quota shall be reported separately" —
        // whatever is left once the target is met, never folded into the
        // capped achievement percentage.
        $additionalCustomers = max(0, $customers - $customerTarget);
        $additionalRevenue = max(0.0, $revenue - $revenueTarget);

        return [
            'customers' => $customers,
            'revenue' => round($revenue, 2),
            'additional_customers' => $additionalCustomers,
            'additional_revenue' => round($additionalRevenue, 2),
        ];
    }
}
