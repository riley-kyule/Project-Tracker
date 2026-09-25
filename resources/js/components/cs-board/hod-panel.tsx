import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

type Evidence = { id: number; original_name: string };

type Item = {
    id: number;
    section: string;
    name: string;
    metric_type: 'new_sales' | 'renewal' | 'service_quality' | null;
    weight: number;
    target_quantity: number | null;
    achieved_quantity: number | null;
    quantity_unit: string | null;
    due_time: string | null;
    employee_status: string;
    employee_comment: string | null;
    blocker_reason: string | null;
    hod_decision: string | null;
    hod_decision_reason: string | null;
    earned_points: number | null;
    evidence_required: boolean;
    evidence: Evidence[];
};

type TodayRow = {
    card_id: number;
    employee_id: number;
    employee_name: string;
    status: string;
    planned_points: number;
    approved_points: number | null;
    employee_submitted_points: number | null;
    missing_evidence_count: number;
    blocked_items: number;
    items: Item[];
};

type WeeklyRow = {
    card_id: number;
    employee_id: number;
    employee_name: string;
    status: string;
    planned_points: number;
    approved_points: number | null;
    items: Item[];
};

type AwaitingReviewRow = {
    card_id: number;
    employee_id: number;
    employee_name: string;
    work_date: string;
    status: string;
    planned_points: number;
    approved_points: number | null;
    pending_item_count: number;
    items: Item[];
};

type ExceptionRow = {
    employee_id: number;
    employee_name: string;
    total_count: number;
    incomplete_count: number;
    late_count: number;
    rejected_count: number;
    missing_evidence_count: number;
    blocked_count: number;
    late_rate: number | null;
    rejected_rate: number | null;
};

type EmployeeRef = { id: number; full_name: string };
type Template = { id: number; name: string; default_weight: number; requires_quantity: boolean; quantity_unit: string | null };

type TeamPerformance = {
    weeks: string[];
    employees: { employee_id: number; employee_name: string; final_scores: (number | null)[]; average_final_score: number | null }[];
};

type Commercial = {
    week_start_date: string;
    new_customers: number;
    new_customers_target: number;
    new_customer_revenue: number;
    new_customer_revenue_target: number;
    renewed_customers: number;
    renewed_customers_target: number;
    retained_revenue: number;
    retained_revenue_target: number;
    currency: string;
};

type HistoryCard = {
    card_id: number;
    work_date: string;
    status: string;
    approved_points: number | null;
    employee_submitted_points: number | null;
    items: Item[];
};

type HistorySummary = {
    employee_name: string;
    on_time_count: number;
    correction_count: number;
    rejection_count: number;
    decided_count: number;
    on_time_rate: number | null;
    correction_rate: number | null;
    rejection_rate: number | null;
    blocked_points: number;
    missing_evidence_count: number;
    commercial: Commercial[];
    cards: HistoryCard[];
    weekly_final_scores: { week_start_date: string; final_score: number | null; is_final: boolean }[];
};

type PendingSale = {
    id: number;
    employee_id: number;
    employee_name: string;
    category: string;
    customer_identifier: string;
    contact_channel: string | null;
    payment_reference: string;
    amount: number;
    currency: string;
    week_start_date: string;
    evidence: Evidence[];
};

type PlatformAssignment = {
    id: number;
    employee_id: number;
    employee_name: string;
    website_id: number | null;
    website_domain: string | null;
    country: string | null;
    backup_employee_name: string | null;
    effective_from: string;
};

type OpenComplaint = {
    id: number;
    employee_id: number;
    employee_name: string;
    description: string;
    reported_at: string;
    service_interaction_channel: string | null;
    evidence: Evidence[];
};

export type CsHodPanelProps = {
    department: { id: number; name: string };
    employees: EmployeeRef[];
    today: TodayRow[];
    awaitingReview: AwaitingReviewRow[];
    weekly: WeeklyRow[];
    exceptions: ExceptionRow[];
    teamPerformance: TeamPerformance;
    teamWeeks: number;
    history: { summary: HistorySummary; range: { from: string; to: string; period: string } } | null;
    pendingSales: PendingSale[];
    platformAssignments: PlatformAssignment[];
    openComplaints: OpenComplaint[];
    dailyTemplates: Template[];
    weeklyTemplates: Template[];
    notifications: unknown | null;
    calibrationEndsAt: string | null;
    reportingCurrency: string;
    /** Which page prop carries this panel's data, for partial reloads. Defaults to My Department's `csBoard`. */
    reloadKey?: string;
};

const fmtRate = (rate: number | null) => (rate === null ? '—' : `${rate}%`);

const DECISIONS: [string, string][] = [
    ['approved', 'Approved — 100%'],
    ['approved_late', 'Approved (late) — 80%'],
    ['minor_correction', 'Minor correction — 75%'],
    ['major_rework', 'Major rework — 50%'],
    ['rejected', 'Rejected — 0%'],
    ['exempted', 'Exempted'],
    ['excluded', 'Excluded from denominator'],
    ['carried_forward', 'Carried forward'],
];
const today = () => new Date().toISOString().slice(0, 10);

const currentWeekStart = () => {
    const d = new Date();
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    return d.toISOString().slice(0, 10);
};

/** Reloads just this panel's prop on whatever page embeds it: `hodBoard` on the Kanban board page, `csBoard` on My Department. */
function reloadCsBoard(extra: Record<string, string | number>, reloadKey: string) {
    const params = new URLSearchParams(window.location.search);
    Object.entries(extra).forEach(([k, v]) => params.set(k, String(v)));
    router.get(window.location.pathname, Object.fromEntries(params), { preserveState: true, preserveScroll: true, only: [reloadKey] });
}

function DecideRow({ item }: { item: Item }) {
    const [decision, setDecision] = useState('approved');
    const [reason, setReason] = useState('');
    const [accepted, setAccepted] = useState(item.achieved_quantity?.toString() ?? '');
    const [processing, setProcessing] = useState(false);
    const isAutoCalculated = item.metric_type !== null;

    const decide = () => {
        setProcessing(true);
        router.post(
            `/cs-board/items/${item.id}/decide`,
            { decision, reason: reason || undefined, accepted_quantity: isAutoCalculated ? undefined : accepted || undefined },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="flex flex-wrap items-end gap-2 border-b py-3 last:border-b-0">
            <div className="grow">
                <div className="font-medium">{item.name}</div>
                <div className="text-muted-foreground text-xs">
                    employee: {item.employee_status} · weight {item.weight}
                    {isAutoCalculated
                        ? ` · ${item.achieved_quantity ?? 0}% ${item.metric_type === 'service_quality' ? 'service quality' : 'commercial'} achievement (auto-calculated)`
                        : item.target_quantity !== null
                          ? ` · ${item.achieved_quantity ?? 0} / ${item.target_quantity} ${item.quantity_unit ?? ''}`
                          : ''}
                    {item.due_time ? ` · due ${item.due_time}` : ''}
                </div>
                {item.blocker_reason && <div className="text-destructive text-xs">Blocker: {item.blocker_reason}</div>}
                {item.evidence.length > 0 && (
                    <div className="mt-1 flex gap-2">
                        {item.evidence.map((e) => (
                            <a key={e.id} href={`/attachments/${e.id}`} className="text-xs underline">
                                {e.original_name}
                            </a>
                        ))}
                    </div>
                )}
            </div>
            {!isAutoCalculated && item.target_quantity !== null && (
                <div>
                    <Label className="text-xs">Accepted qty</Label>
                    <Input className="h-8 w-20" value={accepted} onChange={(e) => setAccepted(e.target.value)} />
                </div>
            )}
            <div>
                <Label className="text-xs">Decision</Label>
                <select
                    className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
                    value={decision}
                    onChange={(e) => setDecision(e.target.value)}
                >
                    {DECISIONS.map(([v, label]) => (
                        <option key={v} value={v}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <Label className="text-xs">Reason</Label>
                <Input className="h-8 w-48" value={reason} onChange={(e) => setReason(e.target.value)} />
            </div>
            <Button size="sm" disabled={processing} onClick={decide}>
                {item.hod_decision ? 'Re-decide' : 'Decide'}
            </Button>
        </div>
    );
}

type SelectedItem = { target_quantity: string; due_time: string };

/** Builds a weekly plan from the template library — every template's own default weight is used as-is (Customer Service Board Spec §6's weekly weights are fixed, unlike SEO's flexible production band). */
function WeeklyAssignPanel({ endpoint, templates }: { endpoint: string; templates: Template[] }) {
    const [selected, setSelected] = useState<Record<number, SelectedItem>>({});
    const [processing, setProcessing] = useState(false);

    const toggle = (t: Template) => {
        setSelected((s) => {
            const next = { ...s };
            if (t.id in next) delete next[t.id];
            else next[t.id] = { target_quantity: '', due_time: '' };
            return next;
        });
    };

    const update = (id: number, patch: Partial<SelectedItem>) => setSelected((s) => ({ ...s, [id]: { ...s[id], ...patch } }));

    const total = templates.filter((t) => t.id in selected).reduce((a, t) => a + t.default_weight, 0);

    const submit = () => {
        setProcessing(true);
        router.post(
            endpoint,
            {
                items: Object.entries(selected).map(([template_id, item]) => ({
                    template_id: Number(template_id),
                    target_quantity: item.target_quantity || undefined,
                    due_time: item.due_time || undefined,
                })),
            },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card className="p-3">
            <div className="mb-2 flex items-center justify-between">
                <h4 className="text-sm font-semibold">Build the weekly plan</h4>
                <Badge variant={total === 100 ? 'default' : 'destructive'}>{total} / 100</Badge>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                {templates.map((t) => (
                    <label key={t.id} className="flex flex-col gap-1.5 rounded-md border p-2 text-sm">
                        <span className="flex items-center gap-2">
                            <input type="checkbox" checked={t.id in selected} onChange={() => toggle(t)} />
                            <span className="grow">
                                {t.name} <span className="text-muted-foreground">({t.default_weight} pts)</span>
                            </span>
                        </span>
                        {t.id in selected && t.requires_quantity && (
                            <span className="flex gap-1.5 pl-6">
                                <Input
                                    placeholder={`Target (${t.quantity_unit ?? 'units'})`}
                                    className="h-7 text-xs"
                                    value={selected[t.id].target_quantity}
                                    onChange={(e) => update(t.id, { target_quantity: e.target.value })}
                                />
                            </span>
                        )}
                    </label>
                ))}
            </div>
            <Button className="mt-3" size="sm" disabled={processing || Object.keys(selected).length === 0} onClick={submit}>
                Save plan
            </Button>
        </Card>
    );
}

function WeeklyTargetForm({ employeeId, reportingCurrency }: { employeeId: number; reportingCurrency: string }) {
    const [newCustomers, setNewCustomers] = useState('0');
    const [newRevenue, setNewRevenue] = useState('0');
    const [renewedCustomers, setRenewedCustomers] = useState('0');
    const [retainedRevenue, setRetainedRevenue] = useState('0');
    const [currency, setCurrency] = useState(reportingCurrency);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            `/cs-board/employees/${employeeId}/weekly-target`,
            {
                week_start_date: currentWeekStart(),
                new_customers_target: newCustomers,
                new_customer_revenue_target: newRevenue,
                renewed_customers_target: renewedCustomers,
                retained_revenue_target: retainedRevenue,
                currency,
                reason: reason || undefined,
            },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card className="p-3">
            <h4 className="mb-2 text-sm font-semibold">This week's commercial targets</h4>
            <div className="grid gap-2 sm:grid-cols-3">
                <div>
                    <Label className="text-xs">New customers (count)</Label>
                    <Input className="h-8" value={newCustomers} onChange={(e) => setNewCustomers(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">New customer revenue</Label>
                    <Input className="h-8" value={newRevenue} onChange={(e) => setNewRevenue(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Renewed/reactivated (count)</Label>
                    <Input className="h-8" value={renewedCustomers} onChange={(e) => setRenewedCustomers(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Retained/recovered revenue</Label>
                    <Input className="h-8" value={retainedRevenue} onChange={(e) => setRetainedRevenue(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Currency</Label>
                    <Input className="h-8" value={currency} onChange={(e) => setCurrency(e.target.value.toUpperCase())} maxLength={3} />
                </div>
                <div>
                    <Label className="text-xs">Reason (required if changing mid-week)</Label>
                    <Input className="h-8" value={reason} onChange={(e) => setReason(e.target.value)} />
                </div>
            </div>
            <Button className="mt-3" size="sm" disabled={processing} onClick={submit}>
                Save targets
            </Button>
        </Card>
    );
}

function TodayRowView({ row }: { row: TodayRow }) {
    const [open, setOpen] = useState(false);

    const reopen = () => {
        const reason = window.prompt('Reason for reopening this closed card:');
        if (!reason) return;
        router.post(`/cs-board/hod/daily/${row.card_id}/reopen`, { reason }, { preserveScroll: true });
    };

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="flex items-center gap-1.5 font-medium">
                    <ChevronRight className={`text-muted-foreground size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                    {row.employee_name}
                </span>
                <span className="flex gap-2">
                    <Badge variant="outline">{row.status}</Badge>
                    <Badge>{row.approved_points ?? row.employee_submitted_points ?? '—'}</Badge>
                    {row.missing_evidence_count > 0 && <Badge variant="destructive">{row.missing_evidence_count} missing evidence</Badge>}
                    {row.blocked_items > 0 && <Badge variant="destructive">{row.blocked_items} blocked</Badge>}
                </span>
            </button>
            {open && (
                <div className="flex flex-col gap-3 px-3 pb-4">
                    <div className="flex items-center justify-between">
                        <Badge variant="outline">planned {row.planned_points}</Badge>
                        {row.status === 'closed' && (
                            <Button size="sm" variant="outline" onClick={reopen}>
                                Reopen
                            </Button>
                        )}
                    </div>
                    {row.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

function AwaitingReviewRowView({ row }: { row: AwaitingReviewRow }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="flex items-center gap-1.5 font-medium">
                    <ChevronRight className={`text-muted-foreground size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                    {row.employee_name} — {fmtDate(row.work_date)}
                </span>
                <span className="flex gap-2">
                    <Badge variant="outline">{row.status}</Badge>
                    <Badge variant="destructive">{row.pending_item_count} pending</Badge>
                    <Badge>{row.approved_points ?? '—'}</Badge>
                </span>
            </button>
            {open && (
                <div className="flex flex-col gap-3 px-3 pb-4">
                    {row.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

function HistoryCardRowView({ card }: { card: HistoryCard }) {
    const [open, setOpen] = useState(false);
    const hasPending = card.items.some((i) => i.hod_decision === null);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full items-center justify-between gap-2 py-1.5 text-left text-sm"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="flex items-center gap-1.5">
                    <ChevronRight className={`text-muted-foreground size-3.5 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                    {fmtDate(card.work_date)}
                </span>
                <span className="flex gap-2">
                    <Badge variant="outline">{card.status}</Badge>
                    {hasPending && <Badge variant="destructive">pending review</Badge>}
                    <Badge>{card.approved_points ?? card.employee_submitted_points ?? '—'}</Badge>
                </span>
            </button>
            {open && (
                <div className="flex flex-col gap-3 pb-3">
                    {card.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                    {card.items.length === 0 && <p className="text-muted-foreground text-sm">No items recorded.</p>}
                </div>
            )}
        </div>
    );
}

function NewWeeklyPlanRow({ employee, templates, reportingCurrency }: { employee: EmployeeRef; templates: Template[]; reportingCurrency: string }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="flex items-center gap-1.5 font-medium">
                    <ChevronRight className={`text-muted-foreground size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                    {employee.full_name}
                </span>
                <Badge className="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    needs assignment
                </Badge>
            </button>
            {open && (
                <div className="flex flex-col gap-3 px-3 pb-4">
                    <WeeklyTargetForm employeeId={employee.id} reportingCurrency={reportingCurrency} />
                    <WeeklyAssignPanel endpoint={`/cs-board/hod/weekly/${employee.id}/${currentWeekStart()}/assign-items`} templates={templates} />
                </div>
            )}
        </div>
    );
}

function WeeklyRowView({ row, templates, reportingCurrency }: { row: WeeklyRow; templates: Template[]; reportingCurrency: string }) {
    const [open, setOpen] = useState(false);

    const approvePlan = () => router.post(`/cs-board/hod/weekly/${row.card_id}/approve-plan`, {}, { preserveScroll: true });

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="flex items-center gap-1.5 font-medium">
                    <ChevronRight className={`text-muted-foreground size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
                    {row.employee_name}
                </span>
                <span className="flex gap-2">
                    {row.status === 'draft' && (
                        <Badge className="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            needs approval
                        </Badge>
                    )}
                    <Badge variant="outline">{row.status}</Badge>
                    <Badge>{row.approved_points ?? '—'}</Badge>
                </span>
            </button>
            {open && (
                <div className="flex flex-col gap-3 px-3 pb-4">
                    <div className="flex items-center justify-between">
                        <Badge variant="outline">planned {row.planned_points}</Badge>
                        {row.status === 'draft' && (
                            <Button size="sm" onClick={approvePlan}>
                                Approve plan
                            </Button>
                        )}
                    </div>
                    <WeeklyTargetForm employeeId={row.employee_id} reportingCurrency={reportingCurrency} />
                    {row.status === 'draft' && (
                        <WeeklyAssignPanel
                            endpoint={`/cs-board/hod/weekly/${row.employee_id}/${currentWeekStart()}/assign-items`}
                            templates={templates}
                        />
                    )}
                    {row.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

function PendingSaleRow({ sale }: { sale: PendingSale }) {
    const [flagging, setFlagging] = useState(false);
    const [flagStatus, setFlagStatus] = useState('reversed');
    const [flagReason, setFlagReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const clear = () => {
        setProcessing(true);
        router.post(`/cs-board/sales/${sale.id}/clear`, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const flag = () => {
        if (!flagReason) return;
        setProcessing(true);
        router.post(
            `/cs-board/sales/${sale.id}/flag`,
            { status: flagStatus, reason: flagReason },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="flex flex-col gap-2 border-b p-3 text-sm last:border-b-0">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span className="font-medium">{sale.employee_name}</span> — {sale.category} — {sale.customer_identifier}
                </div>
                <Badge variant="outline">
                    {sale.currency} {sale.amount.toLocaleString()}
                </Badge>
            </div>
            <div className="text-muted-foreground text-xs">
                ref {sale.payment_reference} · week of {fmtDate(sale.week_start_date)}
                {sale.contact_channel ? ` · ${sale.contact_channel}` : ''}
            </div>
            {sale.evidence.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {sale.evidence.map((e) => (
                        <a key={e.id} href={`/attachments/${e.id}`} className="text-xs underline">
                            {e.original_name}
                        </a>
                    ))}
                </div>
            ) : (
                <Badge variant="destructive" className="w-fit">
                    no evidence attached
                </Badge>
            )}
            <div className="flex flex-wrap items-center gap-2">
                <Button size="sm" disabled={processing} onClick={clear}>
                    Clear — counts toward target
                </Button>
                <Button size="sm" variant="outline" disabled={processing} onClick={() => setFlagging((v) => !v)}>
                    Flag
                </Button>
                {flagging && (
                    <>
                        <select
                            className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
                            value={flagStatus}
                            onChange={(e) => setFlagStatus(e.target.value)}
                        >
                            <option value="reversed">Reversed</option>
                            <option value="refunded">Refunded</option>
                            <option value="fraudulent">Fraudulent</option>
                        </select>
                        <Input className="h-8 w-48" placeholder="Reason" value={flagReason} onChange={(e) => setFlagReason(e.target.value)} />
                        <Button size="sm" variant="destructive" disabled={processing || !flagReason} onClick={flag}>
                            Confirm flag
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function ComplaintRow({ complaint }: { complaint: OpenComplaint }) {
    const [decision, setDecision] = useState('');
    const [processing, setProcessing] = useState(false);

    const decide = (status: string) => {
        if (!decision) return;
        setProcessing(true);
        router.post(
            `/cs-board/complaints/${complaint.id}/decide`,
            { status, decision },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="flex flex-col gap-2 border-b p-3 text-sm last:border-b-0">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">{complaint.employee_name}</span>
                <span className="text-muted-foreground text-xs">
                    {fmtDate(complaint.reported_at)}
                    {complaint.service_interaction_channel ? ` · ${complaint.service_interaction_channel}` : ''}
                </span>
            </div>
            <p>{complaint.description}</p>
            {complaint.evidence.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {complaint.evidence.map((e) => (
                        <a key={e.id} href={`/attachments/${e.id}`} className="text-xs underline">
                            {e.original_name}
                        </a>
                    ))}
                </div>
            )}
            <div className="flex flex-wrap items-center gap-2">
                <Input className="h-8 w-64" placeholder="Decision notes" value={decision} onChange={(e) => setDecision(e.target.value)} />
                <Button size="sm" variant="destructive" disabled={processing || !decision} onClick={() => decide('substantiated')}>
                    Substantiated
                </Button>
                <Button size="sm" variant="outline" disabled={processing || !decision} onClick={() => decide('unsubstantiated')}>
                    Unsubstantiated
                </Button>
            </div>
        </div>
    );
}

function LogComplaintForm({ employees }: { employees: EmployeeRef[] }) {
    const [employeeId, setEmployeeId] = useState('');
    const [description, setDescription] = useState('');
    const [processing, setProcessing] = useState(false);
    const options = employees.map((e) => ({ value: String(e.id), label: e.full_name }));

    const submit = () => {
        setProcessing(true);
        router.post(
            `/cs-board/employees/${employeeId}/complaints`,
            { description },
            { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: () => setDescription('') },
        );
    };

    return (
        <Card className="flex flex-col gap-2 p-3">
            <h4 className="text-sm font-semibold">Log a complaint</h4>
            <div className="flex flex-wrap items-end gap-2">
                <div className="w-56">
                    <Label className="text-xs">Employee</Label>
                    <Combobox value={employeeId} onChange={setEmployeeId} options={options} placeholder="Select employee…" />
                </div>
                <div className="grow">
                    <Label className="text-xs">Description</Label>
                    <Input className="h-9" value={description} onChange={(e) => setDescription(e.target.value)} />
                </div>
                <Button size="sm" disabled={processing || !employeeId || !description} onClick={submit}>
                    Log complaint
                </Button>
            </div>
        </Card>
    );
}

function LogQualityReviewForm({ employees }: { employees: EmployeeRef[] }) {
    const [employeeId, setEmployeeId] = useState('');
    const [channel, setChannel] = useState('call');
    const [accuracyOk, setAccuracyOk] = useState(true);
    const [professionalismOk, setProfessionalismOk] = useState(true);
    const [policyComplianceOk, setPolicyComplianceOk] = useState(true);
    const [correctAdviceOk, setCorrectAdviceOk] = useState(true);
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const options = employees.map((e) => ({ value: String(e.id), label: e.full_name }));

    const submit = () => {
        setProcessing(true);
        router.post(
            `/cs-board/employees/${employeeId}/contact-quality-reviews`,
            {
                channel,
                accuracy_ok: accuracyOk,
                professionalism_ok: professionalismOk,
                policy_compliance_ok: policyComplianceOk,
                correct_advice_ok: correctAdviceOk,
                notes: notes || undefined,
            },
            { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: () => setNotes('') },
        );
    };

    return (
        <Card className="flex flex-col gap-2 p-3">
            <h4 className="text-sm font-semibold">Record a sampled contact review</h4>
            <div className="flex flex-wrap items-end gap-2">
                <div className="w-56">
                    <Label className="text-xs">Employee</Label>
                    <Combobox value={employeeId} onChange={setEmployeeId} options={options} placeholder="Select employee…" />
                </div>
                <div>
                    <Label className="text-xs">Channel</Label>
                    <select
                        className="border-input h-9 rounded-md border bg-transparent px-2 text-sm"
                        value={channel}
                        onChange={(e) => setChannel(e.target.value)}
                    >
                        {['chat', 'call', 'email', 'other'].map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="flex flex-wrap gap-4 text-sm">
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={accuracyOk} onChange={(e) => setAccuracyOk(e.target.checked)} /> Accurate
                </label>
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={professionalismOk} onChange={(e) => setProfessionalismOk(e.target.checked)} /> Professional
                </label>
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={policyComplianceOk} onChange={(e) => setPolicyComplianceOk(e.target.checked)} /> Policy compliant
                </label>
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={correctAdviceOk} onChange={(e) => setCorrectAdviceOk(e.target.checked)} /> Correct advice
                </label>
            </div>
            <div className="flex items-end gap-2">
                <div className="grow">
                    <Label className="text-xs">Notes (optional)</Label>
                    <Input className="h-9" value={notes} onChange={(e) => setNotes(e.target.value)} />
                </div>
                <Button size="sm" disabled={processing || !employeeId} onClick={submit}>
                    Save review
                </Button>
            </div>
        </Card>
    );
}

function AssignmentForm({ employees }: { employees: EmployeeRef[] }) {
    const [employeeId, setEmployeeId] = useState('');
    const [country, setCountry] = useState('');
    const [effectiveFrom, setEffectiveFrom] = useState(today());
    const [backupEmployeeId, setBackupEmployeeId] = useState('');
    const [processing, setProcessing] = useState(false);

    const options = employees.map((e) => ({ value: String(e.id), label: e.full_name }));
    const backupOptions = employees.filter((e) => String(e.id) !== employeeId).map((e) => ({ value: String(e.id), label: e.full_name }));

    const submit = () => {
        setProcessing(true);
        router.post(
            '/cs-board/platform-assignments',
            {
                employee_id: employeeId,
                country: country || undefined,
                effective_from: effectiveFrom,
                backup_employee_id: backupEmployeeId || undefined,
            },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card className="p-3">
            <h4 className="mb-2 text-sm font-semibold">Assign a platform / country</h4>
            <p className="text-muted-foreground mb-2 text-xs">
                Enter the country as free text — website/platform linking is available once the website is registered in EWMS.
            </p>
            <div className="grid gap-2 sm:grid-cols-2">
                <div>
                    <Label className="text-xs">Employee</Label>
                    <Combobox value={employeeId} onChange={setEmployeeId} options={options} placeholder="Select employee…" />
                </div>
                <div>
                    <Label className="text-xs">Country</Label>
                    <Input className="h-9" value={country} onChange={(e) => setCountry(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Effective from</Label>
                    <Input type="date" className="h-9" value={effectiveFrom} onChange={(e) => setEffectiveFrom(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Backup employee (optional)</Label>
                    <Combobox value={backupEmployeeId} onChange={setBackupEmployeeId} options={backupOptions} placeholder="Select backup…" />
                </div>
            </div>
            <Button className="mt-3" size="sm" disabled={processing || !employeeId || !country} onClick={submit}>
                Save assignment
            </Button>
        </Card>
    );
}

type HodTab = 'today' | 'week' | 'sales' | 'quality' | 'assignments' | 'exceptions' | 'trends' | 'history';

export function CsHodPanel({
    department,
    employees,
    today: todayRows,
    awaitingReview,
    weekly,
    exceptions,
    teamPerformance,
    teamWeeks,
    history,
    pendingSales,
    platformAssignments,
    openComplaints,
    weeklyTemplates,
    calibrationEndsAt,
    reportingCurrency,
    reloadKey = 'csBoard',
}: CsHodPanelProps) {
    const [tab, setTab] = useState<HodTab>('today');
    const inCalibration = calibrationEndsAt !== null && calibrationEndsAt >= today();
    const setTeamWeeks = (weeks: number) => reloadCsBoard({ cs_team_weeks: weeks }, reloadKey);

    const [historyEmployeeId, setHistoryEmployeeId] = useState(employees[0]?.id ?? 0);
    const loadHistory = (employeeId: number, period?: string) =>
        reloadCsBoard({ cs_employee_id: employeeId, cs_period: period ?? history?.range.period ?? 'week' }, reloadKey);

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-4 rounded-xl border p-4">
            {inCalibration && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    Calibration period until {calibrationEndsAt} — scores are visible but must not alone drive disciplinary or remuneration decisions
                    yet.
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">Customer Service Board — {department.name}</h2>
                <Link href="/cs-board/settings" className="text-sm underline">
                    Settings
                </Link>
            </div>

            <div className="flex flex-wrap gap-1 border-b">
                {(['today', 'week', 'sales', 'quality', 'assignments', 'exceptions', 'trends', 'history'] as const).map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`px-3 py-2 text-sm font-medium ${tab === t ? 'border-primary text-primary border-b-2' : 'text-muted-foreground'}`}
                    >
                        {t === 'today'
                            ? 'Today'
                            : t === 'week'
                              ? 'This Week'
                              : t === 'sales'
                                ? `Sales${pendingSales.length > 0 ? ` (${pendingSales.length})` : ''}`
                                : t === 'quality'
                                  ? `Quality${openComplaints.length > 0 ? ` (${openComplaints.length})` : ''}`
                                  : t === 'assignments'
                                    ? 'Assignments'
                                    : t === 'exceptions'
                                      ? 'Exceptions'
                                      : t === 'trends'
                                        ? 'Trends'
                                        : 'History'}
                    </button>
                ))}
            </div>

            {tab === 'today' && (
                <div className="flex flex-col gap-4">
                    {awaitingReview.length > 0 && (
                        <Card>
                            <div className="border-b p-3">
                                <h3 className="text-sm font-semibold">Awaiting your review from previous days</h3>
                                <p className="text-muted-foreground text-xs">Left pending when that day closed — still decidable here, never lost.</p>
                            </div>
                            {awaitingReview.map((row) => (
                                <AwaitingReviewRowView key={row.card_id} row={row} />
                            ))}
                        </Card>
                    )}
                    <Card>
                        {todayRows.length === 0 && <p className="text-muted-foreground p-4 text-sm">No daily cards yet today.</p>}
                        {todayRows.map((row) => (
                            <TodayRowView key={row.card_id} row={row} />
                        ))}
                    </Card>
                </div>
            )}

            {tab === 'week' && (
                <Card>
                    {weekly.map((row) => (
                        <WeeklyRowView key={row.card_id} row={row} templates={weeklyTemplates} reportingCurrency={reportingCurrency} />
                    ))}
                    {employees
                        .filter((e) => !weekly.some((w) => w.employee_id === e.id))
                        .map((e) => (
                            <NewWeeklyPlanRow key={e.id} employee={e} templates={weeklyTemplates} reportingCurrency={reportingCurrency} />
                        ))}
                    {weekly.length === 0 && employees.length === 0 && (
                        <p className="text-muted-foreground p-4 text-sm">No employees in this department yet.</p>
                    )}
                </Card>
            )}

            {tab === 'sales' && (
                <Card>
                    {pendingSales.length === 0 && <p className="text-muted-foreground p-4 text-sm">No sales awaiting clearance.</p>}
                    {pendingSales.map((sale) => (
                        <PendingSaleRow key={sale.id} sale={sale} />
                    ))}
                </Card>
            )}

            {tab === 'quality' && (
                <div className="flex flex-col gap-4">
                    <p className="text-muted-foreground text-sm">
                        Response time, resolution and complaints are logged by employees on their own board and computed automatically into the weekly
                        "Customer-service quality" item. Complaints and sampled contact reviews are logged here.
                    </p>
                    <LogComplaintForm employees={employees} />
                    <Card>
                        <div className="border-b p-3">
                            <h3 className="text-sm font-semibold">Open complaints</h3>
                        </div>
                        {openComplaints.length === 0 && <p className="text-muted-foreground p-4 text-sm">No open complaints.</p>}
                        {openComplaints.map((c) => (
                            <ComplaintRow key={c.id} complaint={c} />
                        ))}
                    </Card>
                    <LogQualityReviewForm employees={employees} />
                </div>
            )}

            {tab === 'assignments' && (
                <div className="flex flex-col gap-4">
                    <AssignmentForm employees={employees} />
                    <Card className="p-4">
                        <h3 className="mb-2 text-sm font-semibold">Current assignments</h3>
                        <div className="flex flex-col gap-2">
                            {platformAssignments.map((a) => (
                                <div key={a.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 text-sm">
                                    <span>
                                        <span className="font-medium">{a.employee_name}</span> — {a.website_domain ?? a.country ?? 'unspecified'}
                                        {a.website_domain && a.country ? ` (${a.country})` : ''}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        since {fmtDate(a.effective_from)}
                                        {a.backup_employee_name ? ` · backup: ${a.backup_employee_name}` : ''}
                                    </span>
                                </div>
                            ))}
                            {platformAssignments.length === 0 && <p className="text-muted-foreground text-sm">No assignments yet.</p>}
                        </div>
                    </Card>
                </div>
            )}

            {tab === 'exceptions' && (
                <Card className="p-4">
                    {exceptions.length === 0 && <p className="text-muted-foreground text-sm">No exceptions to review.</p>}
                    <div className="flex flex-col gap-2">
                        {exceptions.map((row) => (
                            <div key={row.employee_id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 text-sm">
                                <span className="font-medium">{row.employee_name}</span>
                                <span className="flex flex-wrap gap-2">
                                    {row.incomplete_count > 0 && <Badge variant="destructive">{row.incomplete_count} incomplete</Badge>}
                                    {row.late_count > 0 && (
                                        <Badge variant="destructive">
                                            {row.late_count} late ({fmtRate(row.late_rate)})
                                        </Badge>
                                    )}
                                    {row.rejected_count > 0 && (
                                        <Badge variant="destructive">
                                            {row.rejected_count} rejected ({fmtRate(row.rejected_rate)})
                                        </Badge>
                                    )}
                                    {row.missing_evidence_count > 0 && (
                                        <Badge variant="destructive">{row.missing_evidence_count} missing evidence</Badge>
                                    )}
                                    {row.blocked_count > 0 && <Badge variant="destructive">{row.blocked_count} blocked</Badge>}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            {tab === 'trends' && (
                <div className="flex flex-col gap-3">
                    <div className="flex gap-1">
                        {[4, 12].map((w) => (
                            <Button key={w} size="sm" variant={teamWeeks === w ? 'default' : 'outline'} onClick={() => setTeamWeeks(w)}>
                                {w} weeks
                            </Button>
                        ))}
                    </div>
                    <Card className="overflow-x-auto p-4">
                        <p className="text-muted-foreground mb-3 text-xs">Weekly final score (70% daily average + 30% weekly) per employee.</p>
                        <table className="w-full min-w-[500px] text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="py-1.5 pr-2 font-medium">Employee</th>
                                    {teamPerformance.weeks.map((w) => (
                                        <th key={w} className="px-2 py-1.5 text-center font-medium whitespace-nowrap">
                                            {fmtDate(w)}
                                        </th>
                                    ))}
                                    <th className="py-1.5 pl-2 text-center font-medium">Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                {teamPerformance.employees.map((row) => (
                                    <tr key={row.employee_id} className="border-b last:border-b-0">
                                        <td className="py-1.5 pr-2 font-medium">{row.employee_name}</td>
                                        {row.final_scores.map((score, i) => (
                                            <td key={i} className="px-2 py-1.5 text-center">
                                                {score ?? '—'}
                                            </td>
                                        ))}
                                        <td className="py-1.5 pl-2 text-center">
                                            <Badge>{row.average_final_score !== null ? Math.round(row.average_final_score * 10) / 10 : '—'}</Badge>
                                        </td>
                                    </tr>
                                ))}
                                {teamPerformance.employees.length === 0 && (
                                    <tr>
                                        <td colSpan={teamPerformance.weeks.length + 2} className="text-muted-foreground py-3 text-center">
                                            No employees in this department yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </Card>
                </div>
            )}

            {tab === 'history' && (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            className="border-input h-9 rounded-md border bg-transparent px-2 text-sm"
                            value={historyEmployeeId}
                            onChange={(e) => {
                                setHistoryEmployeeId(Number(e.target.value));
                                loadHistory(Number(e.target.value));
                            }}
                        >
                            {employees.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.full_name}
                                </option>
                            ))}
                        </select>
                        {['week', 'month', 'quarter'].map((p) => (
                            <Button
                                key={p}
                                size="sm"
                                variant={history?.range.period === p ? 'default' : 'outline'}
                                onClick={() => loadHistory(historyEmployeeId, p)}
                            >
                                {p}
                            </Button>
                        ))}
                    </div>
                    {history === null ? (
                        <p className="text-muted-foreground text-sm">Pick an employee to see their history.</p>
                    ) : (
                        <>
                            <Card className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
                                <div>
                                    <div className="text-muted-foreground text-xs">On-time rate</div>
                                    <div className="text-lg font-semibold">{fmtRate(history.summary.on_time_rate)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {history.summary.on_time_count} of {history.summary.decided_count}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground text-xs">Correction rate</div>
                                    <div className="text-lg font-semibold">{fmtRate(history.summary.correction_rate)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {history.summary.correction_count} of {history.summary.decided_count}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground text-xs">Rejection rate</div>
                                    <div className="text-lg font-semibold">{fmtRate(history.summary.rejection_rate)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {history.summary.rejection_count} of {history.summary.decided_count}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground text-xs">Blocked points</div>
                                    <div className="text-lg font-semibold">{history.summary.blocked_points}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground text-xs">Missing evidence</div>
                                    <div className="text-lg font-semibold">{history.summary.missing_evidence_count}</div>
                                </div>
                            </Card>
                            <Card className="p-4">
                                <h3 className="mb-2 font-semibold">Commercial achievement by week</h3>
                                <div className="flex flex-col gap-2">
                                    {history.summary.commercial.map((c) => (
                                        <div key={c.week_start_date} className="rounded-md border p-2 text-sm">
                                            <div className="mb-1 font-medium">week of {fmtDate(c.week_start_date)}</div>
                                            <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                <span>
                                                    New: {c.new_customers}/{c.new_customers_target} · {c.currency}{' '}
                                                    {c.new_customer_revenue.toLocaleString()}/{c.new_customer_revenue_target.toLocaleString()}
                                                </span>
                                                <span>
                                                    Renewed: {c.renewed_customers}/{c.renewed_customers_target} · {c.currency}{' '}
                                                    {c.retained_revenue.toLocaleString()}/{c.retained_revenue_target.toLocaleString()}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                            <Card className="p-4">
                                <h3 className="mb-2 font-semibold">Daily cards</h3>
                                <p className="text-muted-foreground mb-2 text-xs">Click a day to see its items, evidence, and decisions.</p>
                                <div className="flex flex-col">
                                    {history.summary.cards.map((c) => (
                                        <HistoryCardRowView key={c.card_id} card={c} />
                                    ))}
                                    {history.summary.cards.length === 0 && <p className="text-muted-foreground text-sm">No cards in this range.</p>}
                                </div>
                            </Card>
                            <Card className="p-4">
                                <h3 className="mb-2 font-semibold">Weekly final scores (70% daily + 30% weekly)</h3>
                                <div className="flex flex-col gap-1">
                                    {history.summary.weekly_final_scores.map((s) => (
                                        <div
                                            key={s.week_start_date}
                                            className="flex items-center justify-between border-b py-1 text-sm last:border-b-0"
                                        >
                                            <span>week of {fmtDate(s.week_start_date)}</span>
                                            <Badge variant={s.is_final ? 'default' : 'secondary'}>{s.final_score ?? '—'}</Badge>
                                        </div>
                                    ))}
                                    {history.summary.weekly_final_scores.length === 0 && (
                                        <p className="text-muted-foreground text-sm">No finalised weeks in this range.</p>
                                    )}
                                </div>
                            </Card>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
