<?php

namespace App\Http\Controllers\Hr\Me;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Hr\Payroll\PayslipPdfBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Employee self-service payslips — only periods that have been paid, only the
 * employee's own.
 */
class PayslipController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = $request->user()->employee()->firstOrFail();

        $payslips = Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->whereIn('status', [PayrollPeriod::STATUS_PAID, PayrollPeriod::STATUS_CLOSED]))
            ->with('period:id,label,pay_date,month,year')
            ->get()
            ->sortByDesc(fn (Payslip $p) => [$p->period->year, $p->period->month])
            ->values()
            ->map(fn (Payslip $p) => [
                'id' => $p->id,
                'period' => $p->period->label,
                'pay_date' => $p->period->pay_date->toDateString(),
                'gross_pay' => (float) $p->gross_pay,
                'total_deductions' => (float) $p->total_deductions,
                'net_pay' => (float) $p->net_pay,
                'currency' => $p->currency,
            ]);

        return Inertia::render('hr/me/payslips', ['payslips' => $payslips]);
    }

    public function download(Request $request, Payslip $payslip, PayslipPdfBuilder $builder): HttpResponse
    {
        $employee = $request->user()->employee()->firstOrFail();
        abort_unless($payslip->employee_id === $employee->id, 403);
        abort_unless(in_array($payslip->period->status, [PayrollPeriod::STATUS_PAID, PayrollPeriod::STATUS_CLOSED], true), 403);

        return $builder->stream($payslip);
    }
}
