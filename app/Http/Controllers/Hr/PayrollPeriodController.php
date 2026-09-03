<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePayrollPeriodRequest;
use App\Jobs\ProcessPayrollRun;
use App\Jobs\SendPayslipNotification;
use App\Models\CompanySetting;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\StatutoryRateSet;
use App\Services\AuditLogger;
use App\Services\Hr\Payroll\StatutoryReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPeriodController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PayrollPeriod::class);

        return Inertia::render('hr/payroll/index', [
            'periods' => PayrollPeriod::query()
                ->withCount('payslips')
                ->withSum('payslips as net_total', 'net_pay')
                ->orderByDesc('year')->orderByDesc('month')
                ->get()
                ->map(fn (PayrollPeriod $p) => [
                    'id' => $p->id,
                    'label' => $p->label,
                    'year' => $p->year,
                    'month' => $p->month,
                    'status' => $p->status,
                    'pay_date' => $p->pay_date->toDateString(),
                    'payslips_count' => $p->payslips_count,
                    'net_total' => (float) $p->net_total,
                ]),
        ]);
    }

    public function show(PayrollPeriod $payrollPeriod): Response
    {
        Gate::authorize('view', $payrollPeriod);

        $payrollPeriod->load('rateSet:id,name');

        return Inertia::render('hr/payroll/show', [
            'period' => [
                ...$payrollPeriod->only(['id', 'label', 'year', 'month', 'status', 'notes']),
                'start_date' => $payrollPeriod->start_date->toDateString(),
                'end_date' => $payrollPeriod->end_date->toDateString(),
                'pay_date' => $payrollPeriod->pay_date->toDateString(),
                'processed_at' => $payrollPeriod->processed_at,
                'approved_at' => $payrollPeriod->approved_at,
                'paid_at' => $payrollPeriod->paid_at,
                'rate_set' => $payrollPeriod->rateSet?->only(['id', 'name']),
            ],
            'payslips' => $payrollPeriod->payslips()
                ->with('employee:id,first_name,middle_name,last_name,staff_number')
                ->get()
                ->map(fn (Payslip $p) => [
                    'id' => $p->id,
                    'employee' => $p->employee->full_name,
                    'staff_number' => $p->employee->staff_number,
                    'gross_pay' => (float) $p->gross_pay,
                    'paye' => (float) $p->paye,
                    'nssf_employee' => (float) $p->nssf_employee,
                    'shif_employee' => (float) $p->shif_employee,
                    'housing_levy_employee' => (float) $p->housing_levy_employee,
                    'total_deductions' => (float) $p->total_deductions,
                    'net_pay' => (float) $p->net_pay,
                    'employer_cost' => (float) $p->employer_cost,
                    'has_pdf' => $p->pdf_path !== null,
                ]),
            'totals' => [
                'gross' => (float) $payrollPeriod->payslips()->sum('gross_pay'),
                'paye' => (float) $payrollPeriod->payslips()->sum('paye'),
                'net' => (float) $payrollPeriod->payslips()->sum('net_pay'),
                'employer_cost' => (float) $payrollPeriod->payslips()->sum('employer_cost'),
            ],
            'reports' => StatutoryReportBuilder::REPORTS,
            'requiresSecondApproval' => (bool) CompanySetting::current()->payroll_requires_second_approval,
            'can' => [
                'process' => request()->user()->can('process', $payrollPeriod),
                'approve' => request()->user()->can('approve', $payrollPeriod),
                'markPaid' => request()->user()->can('markPaid', $payrollPeriod),
            ],
        ]);
    }

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        Gate::authorize('create', PayrollPeriod::class);

        $year = $request->integer('year');
        $month = $request->integer('month');
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $payDay = min((int) CompanySetting::current()->default_pay_day ?: 28, $end->day);

        $period = PayrollPeriod::create([
            'year' => $year,
            'month' => $month,
            'label' => $start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'pay_date' => ($request->date('pay_date') ?? $start->copy()->day($payDay))->toDateString(),
            'status' => PayrollPeriod::STATUS_DRAFT,
            'statutory_rate_set_id' => StatutoryRateSet::inForceOn($end)?->id,
            'notes' => $request->input('notes'),
        ]);

        AuditLogger::log($period, 'created', [], ['label' => $period->label]);

        return redirect()->route('hr.payroll.show', $period)->with('success', 'Payroll period created.');
    }

    public function process(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        Gate::authorize('process', $payrollPeriod);

        $payrollPeriod->update(['status' => PayrollPeriod::STATUS_PROCESSING, 'processed_by' => request()->user()->id]);
        ProcessPayrollRun::dispatch($payrollPeriod);

        return back()->with('success', 'Payroll run started — payslips will appear for review shortly.');
    }

    public function approve(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        Gate::authorize('approve', $payrollPeriod);

        $payrollPeriod->update([
            'status' => PayrollPeriod::STATUS_APPROVED,
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);
        AuditLogger::log($payrollPeriod, 'payroll_approved', [], []);

        return back()->with('success', 'Payroll approved.');
    }

    public function markPaid(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        Gate::authorize('markPaid', $payrollPeriod);

        $update = ['status' => PayrollPeriod::STATUS_PAID, 'paid_at' => now()];

        // When no second sign-off is required the HR Manager comes straight
        // from "review" — record them as the approver too.
        if ($payrollPeriod->status === PayrollPeriod::STATUS_REVIEW) {
            $update['approved_by'] = request()->user()->id;
            $update['approved_at'] = now();
        }

        $payrollPeriod->update($update);

        // Payslip emails go out now, or are delayed until the pay date, per
        // the payroll setting.
        $sendAt = CompanySetting::current()->payslip_dispatch_timing === 'on_pay_date'
            && $payrollPeriod->pay_date->isFuture()
                ? $payrollPeriod->pay_date
                : null;

        foreach ($payrollPeriod->payslips()->pluck('id') as $payslipId) {
            SendPayslipNotification::dispatch($payslipId)->delay($sendAt);
        }

        AuditLogger::log($payrollPeriod, 'payroll_paid', [], ['payslips_send_at' => $sendAt?->toDateString() ?? 'now']);

        return back()->with('success', $sendAt
            ? "Marked as paid — payslips will be emailed on {$sendAt->format('d/M/Y')}."
            : 'Marked as paid — payslip emails sent.');
    }

    public function export(PayrollPeriod $payrollPeriod, string $report, StatutoryReportBuilder $builder): StreamedResponse
    {
        Gate::authorize('export', $payrollPeriod);

        return $builder->download($payrollPeriod, $report);
    }
}
