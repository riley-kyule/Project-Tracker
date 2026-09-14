<?php

namespace App\Services\Mcp;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\TrafficDashboardQuery;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The read-only, aggregates-only surface an MCP client (an AI the CEO has
 * connected) can query — see docs/MCP_CONNECTOR.md. Deliberately company-wide
 * sums and counts, never a per-employee row: no individual payslip, no salary
 * figure tied to a name, no raw personal leave/HR record. Everything here is
 * something the dashboards already show in aggregate; this just makes it
 * askable in plain language instead of read off a screen.
 *
 * Each tool is gated by the same permission its dashboard equivalent
 * requires — a token only ever sees what its owner could already see in
 * EWMS itself, including after a permission change made through
 * /admin/permissions.
 */
class McpToolRegistry
{
    private const PROCESSED_PAYROLL_STATUSES = [PayrollPeriod::STATUS_PAID, PayrollPeriod::STATUS_APPROVED, PayrollPeriod::STATUS_CLOSED];

    /** @return array<int, array{name: string, description: string, inputSchema: array}> */
    public function definitions(): array
    {
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['inputSchema'],
        ], $this->tools());
    }

    public function call(User $user, string $name, array $arguments): array
    {
        $tool = collect($this->tools())->firstWhere('name', $name);

        if ($tool === null) {
            throw new McpToolException("Unknown tool: {$name}");
        }

        if (! $user->can($tool['permission'])) {
            throw new McpToolException("Not authorized — this token's owner lacks the '{$tool['permission']}' permission in EWMS.");
        }

        try {
            return ($tool['handler'])($arguments);
        } catch (McpToolException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new McpToolException('This tool failed to run — the underlying data source may be unavailable right now.');
        }
    }

    /** @return array<int, array{name: string, description: string, permission: string, inputSchema: array, handler: callable}> */
    private function tools(): array
    {
        return [
            [
                'name' => 'company_overview',
                'description' => 'Company-wide snapshot: open/overdue/blocked tasks, open tickets, open leave requests, active headcount.',
                'permission' => 'reports.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn () => $this->companyOverview(),
            ],
            [
                'name' => 'department_performance',
                'description' => 'Open/overdue/completed-this-week task counts per department.',
                'permission' => 'reports.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn () => $this->departmentPerformance(),
            ],
            [
                'name' => 'hr_headcount',
                'description' => 'Active employee headcount, broken down by department and by employment type.',
                'permission' => 'hr.employees.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn () => $this->hrHeadcount(),
            ],
            [
                'name' => 'leave_summary',
                'description' => 'Open (pending) and upcoming (next 30 days) leave request counts, and days taken this year by leave type.',
                'permission' => 'hr.leave.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn () => $this->leaveSummary(),
            ],
            [
                'name' => 'payroll_summary',
                'description' => 'Company-wide payroll totals for one period — the most recently paid period if none is given: gross/net/full statutory deduction breakdown (PAYE incl. relief, NSSF, SHIF, housing levy, NITA, employer cost), plus the same broken down per department. Never a per-employee figure.',
                'permission' => 'hr.payroll.view',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['period_label' => ['type' => 'string', 'description' => "A payroll period's label, e.g. \"2026-08\". Omit for the latest paid period."]],
                ],
                'handler' => fn (array $args) => $this->payrollSummary($args['period_label'] ?? null),
            ],
            [
                'name' => 'payroll_trend',
                'description' => 'Company-wide payroll totals (employee count, gross, PAYE, net) for each of the most recent processed periods, oldest first — for spotting month-over-month trends. Never a per-employee figure.',
                'permission' => 'hr.payroll.view',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['periods' => ['type' => 'integer', 'description' => 'How many of the most recent processed periods to include. Defaults to 6.']],
                ],
                'handler' => fn (array $args) => $this->payrollTrend((int) ($args['periods'] ?? 6)),
            ],
            [
                'name' => 'ticket_summary',
                'description' => 'Service desk snapshot: new, unassigned, critical, overdue, waiting, and resolved-today ticket counts.',
                'permission' => 'tickets.manage',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn () => $this->ticketSummary(),
            ],
            [
                'name' => 'traffic_summary',
                'description' => 'GA4 + Search Console totals (users, sessions, clicks, impressions) for a date range, across all connected websites.',
                'permission' => 'view marketing statistics',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string', 'description' => 'Start date, YYYY-MM-DD. Defaults to 7 days ago.'],
                        'to' => ['type' => 'string', 'description' => 'End date, YYYY-MM-DD. Defaults to today.'],
                    ],
                ],
                'handler' => fn (array $args) => $this->trafficSummary($args['from'] ?? null, $args['to'] ?? null),
            ],
        ];
    }

    private function companyOverview(): array
    {
        $open = fn () => Task::query()->whereNull('completed_at')->whereNull('archived_at');

        return [
            'active_employees' => Employee::query()->active()->count(),
            'open_tasks' => $open()->count(),
            'overdue_tasks' => $open()->where('due_at', '<', now())->count(),
            'blocked_tasks' => $open()->whereHas('column', fn ($q) => $q->where('semantic_status', 'blocked'))->count(),
            'completed_tasks_this_week' => Task::query()->where('completed_at', '>=', now()->startOfWeek())->count(),
            'open_tickets' => Ticket::query()->whereIn('status', Ticket::OPEN_STATUSES)->count(),
            'critical_tickets' => Ticket::query()->whereIn('status', Ticket::OPEN_STATUSES)->where('priority', 'critical')->count(),
            'open_leave_requests' => LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
        ];
    }

    private function departmentPerformance(): array
    {
        $open = fn () => Task::query()->whereNull('completed_at')->whereNull('archived_at');

        return Department::query()->active()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Department $department) => [
                'department' => $department->name,
                'open' => (clone $open())->where('department_id', $department->id)->count(),
                'overdue' => (clone $open())->where('department_id', $department->id)->where('due_at', '<', now())->count(),
                'completed_this_week' => Task::query()->where('department_id', $department->id)->where('completed_at', '>=', now()->startOfWeek())->count(),
            ])->all();
    }

    private function hrHeadcount(): array
    {
        $active = Employee::query()->active();

        return [
            'total_active' => (clone $active)->count(),
            'by_department' => (clone $active)
                ->join('departments', 'departments.id', '=', 'employees.department_id')
                ->groupBy('departments.name')
                ->select('departments.name')
                ->selectRaw('count(*) as total')
                ->orderByDesc('total')
                ->pluck('total', 'name'),
            'by_employment_type' => (clone $active)
                ->groupBy('employment_type')
                ->select('employment_type')
                ->selectRaw('count(*) as total')
                ->orderByDesc('total')
                ->pluck('total', 'employment_type'),
        ];
    }

    private function leaveSummary(): array
    {
        return [
            'open_requests' => LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'upcoming_30_days' => LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereBetween('start_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
                ->count(),
            'days_taken_this_year_by_type' => LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereYear('start_date', now()->year)
                ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
                ->groupBy('leave_types.name')
                ->select('leave_types.name')
                ->selectRaw('sum(days) as total_days')
                ->orderByDesc('total_days')
                ->pluck('total_days', 'name'),
        ];
    }

    /**
     * @param  HasMany<Payslip, PayrollPeriod>  $payslips
     * @return array{count: int, gross: float, paye_before_relief: float, personal_relief: float, insurance_relief: float, paye: float, nssf_employee: float, nssf_employer: float, shif_employee: float, housing_levy_employee: float, housing_levy_employer: float, nita_employer: float, net: float, employer_cost: float}
     */
    private function payslipTotals(HasMany $payslips): array
    {
        $totals = $payslips->selectRaw(
            'count(*) as employee_count, sum(gross_pay) as gross_pay, sum(paye_before_relief) as paye_before_relief, '.
            'sum(personal_relief) as personal_relief, sum(insurance_relief) as insurance_relief, sum(paye) as paye, '.
            'sum(nssf_employee) as nssf_employee, sum(nssf_employer) as nssf_employer, sum(shif_employee) as shif_employee, '.
            'sum(housing_levy_employee) as housing_levy_employee, sum(housing_levy_employer) as housing_levy_employer, '.
            'sum(nita_employer) as nita_employer, sum(net_pay) as net_pay, sum(employer_cost) as employer_cost'
        )->first();

        return [
            'count' => (int) $totals->employee_count,
            'gross' => (float) $totals->gross_pay,
            'paye_before_relief' => (float) $totals->paye_before_relief,
            'personal_relief' => (float) $totals->personal_relief,
            'insurance_relief' => (float) $totals->insurance_relief,
            'paye' => (float) $totals->paye,
            'nssf_employee' => (float) $totals->nssf_employee,
            'nssf_employer' => (float) $totals->nssf_employer,
            'shif_employee' => (float) $totals->shif_employee,
            'housing_levy_employee' => (float) $totals->housing_levy_employee,
            'housing_levy_employer' => (float) $totals->housing_levy_employer,
            'nita_employer' => (float) $totals->nita_employer,
            'net' => (float) $totals->net_pay,
            'employer_cost' => (float) $totals->employer_cost,
        ];
    }

    private function payrollSummary(?string $periodLabel): array
    {
        $period = $periodLabel !== null
            ? PayrollPeriod::query()->where('label', $periodLabel)->first()
            : PayrollPeriod::query()->whereIn('status', self::PROCESSED_PAYROLL_STATUSES)->orderByDesc('end_date')->first();

        if ($period === null) {
            throw new McpToolException($periodLabel !== null ? "No payroll period labeled \"{$periodLabel}\"." : 'No processed payroll period exists yet.');
        }

        $companyWide = $this->payslipTotals($period->payslips());

        $byDepartment = $period->payslips()
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->groupBy('departments.name')
            ->select('departments.name')
            ->selectRaw('count(*) as employee_count, sum(gross_pay) as gross_pay, sum(paye) as paye, sum(net_pay) as net_pay')
            ->orderByDesc('gross_pay')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->name,
                'employee_count' => (int) $row->employee_count,
                'total_gross_pay' => (float) $row->gross_pay,
                'total_paye' => (float) $row->paye,
                'total_net_pay' => (float) $row->net_pay,
            ])->all();

        return [
            'period' => $period->label,
            'status' => $period->status,
            'employee_count' => $companyWide['count'],
            'total_gross_pay' => $companyWide['gross'],
            'paye_breakdown' => [
                'paye_before_relief' => $companyWide['paye_before_relief'],
                'personal_relief' => $companyWide['personal_relief'],
                'insurance_relief' => $companyWide['insurance_relief'],
                'paye_after_relief' => $companyWide['paye'],
            ],
            'statutory_deductions' => [
                'nssf_employee' => $companyWide['nssf_employee'],
                'nssf_employer' => $companyWide['nssf_employer'],
                'shif_employee' => $companyWide['shif_employee'],
                'housing_levy_employee' => $companyWide['housing_levy_employee'],
                'housing_levy_employer' => $companyWide['housing_levy_employer'],
                'nita_employer' => $companyWide['nita_employer'],
            ],
            'total_net_pay' => $companyWide['net'],
            'total_employer_cost' => $companyWide['employer_cost'],
            'by_department' => $byDepartment,
        ];
    }

    private function payrollTrend(int $periods): array
    {
        $periods = max(1, min($periods, 24));

        return PayrollPeriod::query()
            ->whereIn('status', self::PROCESSED_PAYROLL_STATUSES)
            ->orderByDesc('end_date')
            ->limit($periods)
            ->get()
            ->reverse()
            ->values()
            ->map(function (PayrollPeriod $period) {
                $totals = $this->payslipTotals($period->payslips());

                return [
                    'period' => $period->label,
                    'employee_count' => $totals['count'],
                    'total_gross_pay' => $totals['gross'],
                    'total_paye' => $totals['paye'],
                    'total_net_pay' => $totals['net'],
                ];
            })->all();
    }

    private function ticketSummary(): array
    {
        $open = Ticket::query()->whereIn('status', Ticket::OPEN_STATUSES);

        return [
            'new' => Ticket::query()->where('status', Ticket::STATUS_NEW)->count(),
            'unassigned_open' => (clone $open)->whereNull('assigned_to')->count(),
            'critical_open' => (clone $open)->where('priority', 'critical')->count(),
            'overdue_open' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'waiting' => Ticket::query()->whereIn('status', [Ticket::STATUS_WAITING_USER, Ticket::STATUS_WAITING_THIRD_PARTY])->count(),
            'resolved_today' => Ticket::query()->whereDate('resolved_at', today())->count(),
        ];
    }

    private function trafficSummary(?string $from, ?string $to): array
    {
        $toDate = $to !== null ? Carbon::parse($to)->startOfDay() : today();
        $fromDate = $from !== null ? Carbon::parse($from)->startOfDay() : $toDate->copy()->subDays(6);

        /** @var TrafficDashboardQuery $ga4 */
        $ga4 = app(TrafficDashboardQuery::class);
        /** @var GscReportQuery $gsc */
        $gsc = app(GscReportQuery::class);

        if (! $ga4->isConfigured() && ! $gsc->isConfigured()) {
            throw new McpToolException('Analytics is not configured on this EWMS instance.');
        }

        $ga4Rows = $ga4->isConfigured() ? $ga4->dailyRows(null, $fromDate, $toDate) : [];
        $gscRows = $gsc->isConfigured() ? $gsc->dailyRows(null, $fromDate, $toDate) : [];

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'ga4_users' => (int) array_sum(array_column($ga4Rows, 'users')),
            'ga4_sessions' => (int) array_sum(array_column($ga4Rows, 'sessions')),
            'gsc_clicks' => (int) array_sum(array_column($gscRows, 'clicks')),
            'gsc_impressions' => (int) array_sum(array_column($gscRows, 'impressions')),
        ];
    }
}
