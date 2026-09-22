<?php

namespace App\Services\Mcp;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SeoDailyCard;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Seo\SeoPerformanceQuery;
use App\Services\TaskService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * The surface an MCP client (an AI someone with mcp.manage has connected)
 * can query and act on — see docs/MCP_CONNECTOR.md. Most tools are
 * aggregates-only and read-only: company-wide sums and counts, never a
 * per-employee row — no individual payslip, no salary figure tied to a
 * name, no raw personal leave/HR record. A handful of tools (create_task,
 * create_ticket) do mutate state; each mirrors the exact validation,
 * authorization, and side effects (notifications, audit log) of its web UI
 * equivalent, via the same service class the controller itself calls
 * (TaskService, TicketService) — never a separate, looser code path.
 *
 * Each tool is gated by the same permission its dashboard/form equivalent
 * requires — a token only ever sees or does what its owner could already
 * see or do in EWMS itself, including after a permission change made
 * through /admin/permissions.
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

        // A null permission means the web equivalent is itself role-blind
        // (e.g. TicketPolicy::create() — any logged-in user may submit a
        // ticket) rather than gated by a specific Spatie permission.
        if ($tool['permission'] !== null && ! $user->can($tool['permission'])) {
            throw new McpToolException("Not authorized — this token's owner lacks the '{$tool['permission']}' permission in EWMS.");
        }

        try {
            return ($tool['handler'])($user, $arguments);
        } catch (McpToolException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new McpToolException('This tool failed to run — the underlying data source may be unavailable right now.');
        }
    }

    /** @return array<int, array{name: string, description: string, permission: ?string, inputSchema: array, handler: callable}> */
    private function tools(): array
    {
        return [
            [
                'name' => 'company_overview',
                'description' => 'Company-wide snapshot: open/overdue/blocked tasks, open tickets, open leave requests, active headcount.',
                'permission' => 'reports.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn (User $user, array $args) => $this->companyOverview(),
            ],
            [
                'name' => 'department_performance',
                'description' => 'Open/overdue/completed-this-week task counts per department.',
                'permission' => 'reports.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn (User $user, array $args) => $this->departmentPerformance(),
            ],
            [
                'name' => 'hr_headcount',
                'description' => 'Active employee headcount, broken down by department and by employment type.',
                'permission' => 'hr.employees.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn (User $user, array $args) => $this->hrHeadcount(),
            ],
            [
                'name' => 'leave_summary',
                'description' => 'Open (pending) and upcoming (next 30 days) leave request counts, and days taken this year by leave type.',
                'permission' => 'hr.leave.view',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn (User $user, array $args) => $this->leaveSummary(),
            ],
            [
                'name' => 'payroll_summary',
                'description' => 'Company-wide payroll totals for one period — the most recently paid period if none is given: gross/net/full statutory deduction breakdown (PAYE incl. relief, NSSF, SHIF, housing levy, NITA, employer cost), plus the same broken down per department. Never a per-employee figure.',
                'permission' => 'hr.payroll.view',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['period_label' => ['type' => 'string', 'description' => "A payroll period's label, e.g. \"2026-08\". Omit for the latest paid period."]],
                ],
                'handler' => fn (User $user, array $args) => $this->payrollSummary($args['period_label'] ?? null),
            ],
            [
                'name' => 'payroll_trend',
                'description' => 'Company-wide payroll totals (employee count, gross, PAYE, net) for each of the most recent processed periods, oldest first — for spotting month-over-month trends. Never a per-employee figure.',
                'permission' => 'hr.payroll.view',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['periods' => ['type' => 'integer', 'description' => 'How many of the most recent processed periods to include. Defaults to 6.']],
                ],
                'handler' => fn (User $user, array $args) => $this->payrollTrend((int) ($args['periods'] ?? 6)),
            ],
            [
                'name' => 'ticket_summary',
                'description' => 'Service desk snapshot: new, unassigned, critical, overdue, waiting, and resolved-today ticket counts.',
                'permission' => 'tickets.manage',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                'handler' => fn (User $user, array $args) => $this->ticketSummary(),
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
                'handler' => fn (User $user, array $args) => $this->trafficSummary($args['from'] ?? null, $args['to'] ?? null),
            ],
            [
                'name' => 'seo_department_performance',
                'description' => 'SEO Board today snapshot for a department (default: SEO): each employee\'s live daily card status, approved/provisional points, missing evidence, overdue and blocked items — plus the last 4 weeks\' exceptions (repeated incompletion, lateness, rejections, missing evidence, recurring blockers). Never HR/payroll data.',
                'permission' => 'seo.cards.view',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['department' => ['type' => 'string', 'description' => "Department name, e.g. \"SEO\". Defaults to \"SEO\"."]],
                ],
                'handler' => fn (User $user, array $args) => $this->seoDepartmentPerformance($user, $args['department'] ?? 'SEO'),
            ],
            [
                'name' => 'seo_employee_performance',
                'description' => 'One SEO employee\'s daily/weekly card history for a date range: planned vs. approved points per day, quota achievement, weighted weekly final scores (70% daily average + 30% weekly, per the SEO Board spec), on-time/late/correction/rejection counts, and blocked points. Never a payslip or salary figure.',
                'permission' => 'seo.cards.view',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['employee'],
                    'properties' => [
                        'employee' => ['type' => 'string', 'description' => "The employee's name or email."],
                        'from' => ['type' => 'string', 'description' => 'Start date, YYYY-MM-DD. Defaults to 7 days ago.'],
                        'to' => ['type' => 'string', 'description' => 'End date, YYYY-MM-DD. Defaults to today.'],
                    ],
                ],
                'handler' => fn (User $user, array $args) => $this->seoEmployeePerformance($user, (string) $args['employee'], $args['from'] ?? null, $args['to'] ?? null),
            ],
            [
                'name' => 'create_task',
                'description' => "Create a task on a board and optionally assign it to someone. board and column must match an existing board's name and one of its columns' names (case-insensitive) — a wrong or ambiguous name errors with the valid options for that board, or a list of board names if the board itself didn't match.",
                'permission' => 'tasks.create',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['board', 'column', 'title'],
                    'properties' => [
                        'board' => ['type' => 'string', 'description' => "The board's name."],
                        'column' => ['type' => 'string', 'description' => "The column's name on that board, e.g. \"To Do\"."],
                        'title' => ['type' => 'string', 'description' => 'Task title.'],
                        'description' => ['type' => 'string', 'description' => 'Optional task description.'],
                        'priority' => ['type' => 'string', 'description' => 'critical, high, medium, or low. Defaults to medium.'],
                        'assignee' => ['type' => 'string', 'description' => "Optional — the assignee's name or email. Must be an active EWMS user; they're notified the same way an in-app assignment notifies them."],
                        'due_at' => ['type' => 'string', 'description' => 'Optional due date, YYYY-MM-DD.'],
                    ],
                ],
                'handler' => fn (User $user, array $args) => $this->createTask($user, $args),
            ],
            [
                'name' => 'create_ticket',
                'description' => "Create a service desk ticket for IT, R&D, or both. Anyone with a connected token can create one — same as EWMS's own ticket form, which is deliberately open to every role.",
                'permission' => null,
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['title', 'description', 'category'],
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Ticket title.'],
                        'description' => ['type' => 'string', 'description' => 'What the issue or request is.'],
                        'category' => ['type' => 'string', 'description' => "The ticket category's name, e.g. \"Hardware\" or \"Access Request\". Must match an existing active category — a wrong name errors with the valid ones."],
                        'impact' => ['type' => 'string', 'description' => 'low, medium, or high. Defaults to medium.'],
                        'team' => ['type' => 'string', 'description' => 'it, rnd, or both — which service desk queue handles it. Defaults to it.'],
                        'requester' => ['type' => 'string', 'description' => "Optional — file this ticket on someone else's behalf, by their name or email. Only honored if the token owner is IT, CEO, or Administrator; ignored otherwise (the ticket is filed under the token owner's own name instead)."],
                    ],
                ],
                'handler' => fn (User $user, array $args) => $this->createTicket($user, $args),
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

    private function seoDepartmentPerformance(User $user, string $departmentNeedle): array
    {
        $department = $this->resolveVisibleDepartment($user, $departmentNeedle);

        $query = app(SeoPerformanceQuery::class);

        return [
            'department' => $department->name,
            'today' => $query->departmentToday($department),
            'weekly_plans' => $query->departmentWeekly($department),
            'exceptions_last_4_weeks' => $query->exceptions($department),
        ];
    }

    private function seoEmployeePerformance(User $user, string $employeeNeedle, ?string $from, ?string $to): array
    {
        $employee = $this->resolveSeoEmployee($user, $employeeNeedle);

        $toDate = $to !== null ? Carbon::parse($to)->startOfDay() : today();
        $fromDate = $from !== null ? Carbon::parse($from)->startOfDay() : $toDate->copy()->subDays(6);

        return app(SeoPerformanceQuery::class)->employeeSummary($employee, $fromDate, $toDate);
    }

    /** Only the SEO (or requested) department if the caller can actually see it — CEO/Admin see any, an HOD only their own mapped department(s), same rule SeoHodController enforces. */
    private function resolveVisibleDepartment(User $user, string $needle): Department
    {
        $needle = trim($needle) ?: 'SEO';

        $department = Department::query()->where('is_active', true)->get()
            ->first(fn (Department $d) => Str::lower($d->name) === Str::lower($needle));

        if ($department === null) {
            throw new McpToolException("No active department named \"{$needle}\".");
        }

        if (! $user->hasAnyRole(['CEO', 'Administrator']) && ! $department->isLedBy($user)) {
            throw new McpToolException("Not authorized — this token's owner is not the HOD for \"{$department->name}\".");
        }

        return $department;
    }

    private function resolveSeoEmployee(User $user, string $needle): Employee
    {
        $needle = trim($needle);
        if ($needle === '') {
            throw new McpToolException('An employee name or email is required.');
        }

        $candidates = Employee::query()->active()->with('user')->get()->filter(function (Employee $employee) use ($user) {
            if ($user->hasAnyRole(['CEO', 'Administrator'])) {
                return true;
            }

            return $employee->department !== null && $employee->department->isLedBy($user);
        });

        $exact = $candidates->first(fn (Employee $e) => Str::lower($e->full_name) === Str::lower($needle) || Str::lower((string) $e->user?->email) === Str::lower($needle));
        if ($exact !== null) {
            return $exact;
        }

        $fuzzy = $candidates->filter(fn (Employee $e) => Str::contains(Str::lower($e->full_name), Str::lower($needle)));
        if ($fuzzy->count() === 1) {
            return $fuzzy->first();
        }
        if ($fuzzy->isEmpty()) {
            throw new McpToolException("No SEO-visible employee found matching \"{$needle}\".");
        }
        throw new McpToolException("Multiple employees match \"{$needle}\": ".$fuzzy->pluck('full_name')->implode(', ').'. Be more specific or use their email.');
    }

    private function createTask(User $user, array $args): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            throw new McpToolException('A title is required.');
        }
        if (strlen($title) > 255) {
            throw new McpToolException('Title must be 255 characters or fewer.');
        }

        $board = $this->resolveBoardForUser($user, (string) ($args['board'] ?? ''));

        if (! Gate::forUser($user)->allows('create', [Task::class, $board])) {
            throw new McpToolException("Not authorized to create tasks on \"{$board->name}\".");
        }

        $column = $this->resolveColumn($board, (string) ($args['column'] ?? ''));

        $priority = strtolower(trim((string) ($args['priority'] ?? 'medium')));
        if (! in_array($priority, Task::PRIORITIES, true)) {
            throw new McpToolException('priority must be one of: '.implode(', ', Task::PRIORITIES).'.');
        }

        $dueAt = null;
        if (! empty($args['due_at'])) {
            try {
                $dueAt = Carbon::parse($args['due_at']);
            } catch (Throwable) {
                throw new McpToolException("Couldn't understand due_at \"{$args['due_at']}\" as a date.");
            }
        }

        $assignee = null;
        if (! empty($args['assignee'])) {
            $assignee = $this->resolveActiveUser((string) $args['assignee']);
        }

        $data = [
            'title' => $title,
            'description' => ! empty($args['description']) ? (string) $args['description'] : null,
            'priority' => $priority,
            'due_at' => $dueAt,
            'primary_assignee_id' => $assignee?->id,
        ];

        $task = TaskService::create($user, $board, $column, $data);

        return [
            'task_id' => $task->id,
            'task_number' => $task->task_number,
            'title' => $task->title,
            'board' => $board->name,
            'column' => $column->name,
            'priority' => $task->priority,
            'assignee' => $assignee?->name,
            'due_at' => $dueAt?->toDateString(),
            'url' => url("/boards/{$board->id}?task={$task->id}"),
        ];
    }

    private function createTicket(User $user, array $args): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        $description = trim((string) ($args['description'] ?? ''));
        if ($title === '' || $description === '') {
            throw new McpToolException('Both title and description are required.');
        }
        if (strlen($title) > 255) {
            throw new McpToolException('Title must be 255 characters or fewer.');
        }
        if (strlen($description) > 20000) {
            throw new McpToolException('Description must be 20000 characters or fewer.');
        }

        $category = $this->resolveTicketCategory((string) ($args['category'] ?? ''));

        $impact = strtolower(trim((string) ($args['impact'] ?? 'medium')));
        if (! in_array($impact, ['low', 'medium', 'high'], true)) {
            throw new McpToolException('impact must be one of: low, medium, high.');
        }

        $team = strtolower(trim((string) ($args['team'] ?? Ticket::TEAM_IT)));
        if (! in_array($team, Ticket::TEAMS, true)) {
            throw new McpToolException('team must be one of: '.implode(', ', Ticket::TEAMS).'.');
        }

        $requester = null;
        if (! empty($args['requester']) && Gate::forUser($user)->allows('createForOthers', Ticket::class)) {
            $requester = $this->resolveActiveUser((string) $args['requester']);
        }

        $ticket = TicketService::submit($user, [
            'title' => $title,
            'description' => $description,
            'category_id' => $category->id,
            'impact' => $impact,
            'team' => $team,
            'priority' => $category->default_priority ?? 'medium',
        ], $requester);

        return [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'category' => $category->name,
            'team' => $ticket->team,
            'priority' => $ticket->priority,
            'requester' => $ticket->requester_id === $user->id ? $user->name : $requester?->name,
            'url' => url("/tickets/{$ticket->id}"),
        ];
    }

    /** Only boards the caller can actually view — a name match against a board they can't see fails the same way a nonexistent board would, so nothing about a restricted board's existence leaks. */
    private function resolveBoardForUser(User $user, string $needle): Board
    {
        $needle = trim($needle);
        if ($needle === '') {
            throw new McpToolException('A board name is required.');
        }

        $visible = Board::query()->where('is_active', true)->get()
            ->filter(fn (Board $board) => Gate::forUser($user)->allows('view', $board));

        $exact = $visible->first(fn (Board $board) => Str::lower($board->name) === Str::lower($needle));
        if ($exact !== null) {
            return $exact;
        }

        $fuzzy = $visible->filter(fn (Board $board) => Str::contains(Str::lower($board->name), Str::lower($needle)));
        if ($fuzzy->count() === 1) {
            return $fuzzy->first();
        }

        $names = ($fuzzy->isNotEmpty() ? $fuzzy : $visible)->pluck('name')->sort()->values();
        if ($names->isEmpty()) {
            throw new McpToolException("No board named \"{$needle}\" — you don't appear to have access to any boards.");
        }
        throw new McpToolException("No board named \"{$needle}\". Boards you can see: ".$names->implode(', ').'.');
    }

    private function resolveColumn(Board $board, string $needle): BoardColumn
    {
        $needle = trim($needle);
        $columns = $board->columns()->orderBy('position')->get();

        if ($needle === '') {
            throw new McpToolException("A column name is required. Columns on \"{$board->name}\": ".$columns->pluck('name')->implode(', ').'.');
        }

        $exact = $columns->first(fn (BoardColumn $column) => Str::lower($column->name) === Str::lower($needle));
        if ($exact !== null) {
            return $exact;
        }

        $fuzzy = $columns->filter(fn (BoardColumn $column) => Str::contains(Str::lower($column->name), Str::lower($needle)));
        if ($fuzzy->count() === 1) {
            return $fuzzy->first();
        }

        throw new McpToolException("No column named \"{$needle}\" on \"{$board->name}\". Columns there: ".$columns->pluck('name')->implode(', ').'.');
    }

    private function resolveTicketCategory(string $needle): TicketCategory
    {
        $needle = trim($needle);
        $active = TicketCategory::query()->where('is_active', true)->get();

        if ($needle === '') {
            throw new McpToolException('A category is required. Active categories: '.$active->pluck('name')->implode(', ').'.');
        }

        $exact = $active->first(fn (TicketCategory $category) => Str::lower($category->name) === Str::lower($needle));
        if ($exact !== null) {
            return $exact;
        }

        $fuzzy = $active->filter(fn (TicketCategory $category) => Str::contains(Str::lower($category->name), Str::lower($needle)));
        if ($fuzzy->count() === 1) {
            return $fuzzy->first();
        }

        throw new McpToolException("No active category named \"{$needle}\". Active categories: ".$active->pluck('name')->implode(', ').'.');
    }

    /** Exact email/name match first, then a fuzzy name fallback — errors list the ambiguous candidates rather than silently guessing. */
    private function resolveActiveUser(string $needle): User
    {
        $needle = trim($needle);
        if ($needle === '') {
            throw new McpToolException('A user name or email is required.');
        }

        $exact = User::query()->where('status', User::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [Str::lower($needle)])->orWhereRaw('LOWER(name) = ?', [Str::lower($needle)]))
            ->get();

        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            throw new McpToolException("Multiple active users match \"{$needle}\": ".$exact->pluck('name')->implode(', ').'. Use their email instead.');
        }

        $fuzzy = User::query()->where('status', User::STATUS_ACTIVE)->where('name', 'like', '%'.$needle.'%')->limit(6)->get();

        if ($fuzzy->count() === 1) {
            return $fuzzy->first();
        }
        if ($fuzzy->isEmpty()) {
            throw new McpToolException("No active user found matching \"{$needle}\".");
        }
        throw new McpToolException("Multiple active users match \"{$needle}\": ".$fuzzy->pluck('name')->implode(', ').'. Be more specific or use their email.');
    }
}
