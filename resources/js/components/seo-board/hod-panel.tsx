import { SeoBoardTour, type TourStep } from '@/components/seo-board/tour';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Evidence = { id: number; original_name: string };

type Item = {
    id: number;
    section: string;
    name: string;
    weight: number;
    target_quantity: number | null;
    achieved_quantity: number | null;
    quantity_unit: string | null;
    assigned_url: string | null;
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
    overdue_items: number;
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
type Template = { id: number; name: string; default_weight: number; min_weight: number; max_weight: number };

type TeamPerformance = {
    weeks: string[];
    employees: { employee_id: number; employee_name: string; final_scores: (number | null)[]; average_final_score: number | null }[];
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
    cards: HistoryCard[];
    weekly_final_scores: { week_start_date: string; final_score: number | null; is_final: boolean }[];
};

export type SeoHodPanelProps = {
    department: { id: number; name: string };
    employees: EmployeeRef[];
    today: TodayRow[];
    awaitingReview: AwaitingReviewRow[];
    weekly: WeeklyRow[];
    exceptions: ExceptionRow[];
    teamPerformance: TeamPerformance;
    teamWeeks: number;
    history: { summary: HistorySummary; range: { from: string; to: string; period: string } } | null;
    productionTemplates: Template[];
    weeklyTemplates: Template[];
    notifications: unknown | null;
    calibrationEndsAt: string | null;
};

/** null (nothing decided yet) reads as "—", not a misleading 0%. */
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

/** Monday of the current week, Y-m-d — matches the backend's Carbon::startOfWeek(). */
const currentWeekStart = () => {
    const d = new Date();
    const day = d.getDay(); // 0 = Sunday
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    return d.toISOString().slice(0, 10);
};

/** Reloads just the seoBoard prop on whatever page this panel is embedded in, preserving other query params (e.g. the department dashboard's own department_id switch). */
function reloadSeoBoard(extra: Record<string, string | number>) {
    const params = new URLSearchParams(window.location.search);
    Object.entries(extra).forEach(([k, v]) => params.set(k, String(v)));
    router.get(window.location.pathname, Object.fromEntries(params), { preserveState: true, preserveScroll: true, only: ['seoBoard'] });
}

function DecideRow({ item }: { item: Item }) {
    const [decision, setDecision] = useState('approved');
    const [reason, setReason] = useState('');
    const [accepted, setAccepted] = useState(item.achieved_quantity?.toString() ?? '');
    const [processing, setProcessing] = useState(false);

    const decide = () => {
        setProcessing(true);
        router.post(
            `/seo-board/items/${item.id}/decide`,
            { decision, reason: reason || undefined, accepted_quantity: accepted || undefined },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="flex flex-wrap items-end gap-2 border-b py-3 last:border-b-0">
            <div className="grow">
                <div className="font-medium">{item.name}</div>
                <div className="text-muted-foreground text-xs">
                    employee: {item.employee_status} · weight {item.weight}
                    {item.target_quantity !== null ? ` · ${item.achieved_quantity ?? 0} / ${item.target_quantity} ${item.quantity_unit ?? ''}` : ''}
                    {item.due_time ? ` · due ${item.due_time}` : ''}
                </div>
                {item.assigned_url && (
                    <a href={item.assigned_url} target="_blank" rel="noreferrer" className="text-xs break-all underline">
                        {item.assigned_url}
                    </a>
                )}
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
            {item.target_quantity !== null && (
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

type SelectedItem = { weight: number; assigned_url: string; due_time: string };

function AssignPanel({ endpoint, templates, fixedPoints = 0 }: { endpoint: string; templates: Template[]; fixedPoints?: number }) {
    const [selected, setSelected] = useState<Record<number, SelectedItem>>({});
    const [processing, setProcessing] = useState(false);

    const toggle = (t: Template) => {
        setSelected((s) => {
            const next = { ...s };
            if (t.id in next) delete next[t.id];
            else next[t.id] = { weight: t.default_weight, assigned_url: '', due_time: '' };
            return next;
        });
    };

    const update = (id: number, patch: Partial<SelectedItem>) => setSelected((s) => ({ ...s, [id]: { ...s[id], ...patch } }));

    const total = Object.values(selected).reduce((a, b) => a + Number(b.weight), 0);
    const grandTotal = fixedPoints + total;

    const submit = () => {
        setProcessing(true);
        router.post(
            endpoint,
            {
                items: Object.entries(selected).map(([template_id, item]) => ({
                    template_id: Number(template_id),
                    ...(fixedPoints > 0 ? { weight: item.weight } : {}),
                    assigned_url: item.assigned_url || undefined,
                    due_time: item.due_time || undefined,
                })),
            },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card className="p-3">
            <div className="mb-2 flex items-center justify-between">
                <h4 className="text-sm font-semibold">Assign items</h4>
                <Badge variant={grandTotal === 100 ? 'default' : 'destructive'}>{grandTotal} / 100</Badge>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                {templates.map((t) => (
                    <label key={t.id} className="flex flex-col gap-1.5 rounded-md border p-2 text-sm">
                        <span className="flex items-center gap-2">
                            <input type="checkbox" checked={t.id in selected} onChange={() => toggle(t)} />
                            <span className="grow">{t.name}</span>
                            {fixedPoints > 0 && t.id in selected && (
                                <Input
                                    type="number"
                                    className="h-7 w-16"
                                    min={t.min_weight}
                                    max={t.max_weight}
                                    value={selected[t.id].weight}
                                    onChange={(e) => update(t.id, { weight: Number(e.target.value) })}
                                />
                            )}
                        </span>
                        {t.id in selected && (
                            <span className="flex gap-1.5 pl-6">
                                <Input
                                    placeholder="Assigned URL (optional)"
                                    className="h-7 text-xs"
                                    value={selected[t.id].assigned_url}
                                    onChange={(e) => update(t.id, { assigned_url: e.target.value })}
                                />
                                <Input
                                    type="time"
                                    className="h-7 w-28 text-xs"
                                    value={selected[t.id].due_time}
                                    onChange={(e) => update(t.id, { due_time: e.target.value })}
                                />
                            </span>
                        )}
                    </label>
                ))}
            </div>
            <Button className="mt-3" size="sm" disabled={processing || Object.keys(selected).length === 0} onClick={submit}>
                Save
            </Button>
        </Card>
    );
}

function TodayRowView({ row, templates }: { row: TodayRow; templates: Template[] }) {
    const [open, setOpen] = useState(false);

    const reopen = () => {
        const reason = window.prompt('Reason for reopening this closed card:');
        if (!reason) return;
        router.post(`/seo-board/hod/daily/${row.card_id}/reopen`, { reason }, { preserveScroll: true });
    };

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="font-medium">{row.employee_name}</span>
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
                    <AssignPanel endpoint={`/seo-board/hod/daily/${row.card_id}/assign-items`} templates={templates} fixedPoints={35} />
                    {row.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * A closed card from a previous day, still showing here because at least one
 * item was never decided — once a day rolls over it drops out of "Today"
 * entirely, so without this row an undecided item would become unreachable.
 * No re-assign panel: item assignment happens before a day begins, not after
 * the fact.
 */
function AwaitingReviewRowView({ row }: { row: AwaitingReviewRow }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="font-medium">
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

/** A single history day, expandable to see (and, if anything was left pending, still decide) exactly what happened — §11.1's "links from summary figures to the archived card". */
function HistoryCardRowView({ card }: { card: HistoryCard }) {
    const [open, setOpen] = useState(false);
    const hasPending = card.items.some((i) => i.hod_decision === null);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full items-center justify-between gap-2 py-1.5 text-left text-sm"
                onClick={() => setOpen((v) => !v)}
            >
                <span>{fmtDate(card.work_date)}</span>
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

function NewWeeklyPlanRow({ employee, templates }: { employee: EmployeeRef; templates: Template[] }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="font-medium">{employee.full_name}</span>
                <Badge variant="secondary">no plan yet</Badge>
            </button>
            {open && (
                <div className="px-3 pb-4">
                    <AssignPanel endpoint={`/seo-board/hod/weekly/${employee.id}/${currentWeekStart()}/assign-items`} templates={templates} />
                </div>
            )}
        </div>
    );
}

function WeeklyRowView({ row, templates }: { row: WeeklyRow; templates: Template[] }) {
    const [open, setOpen] = useState(false);

    const approvePlan = () => router.post(`/seo-board/hod/weekly/${row.card_id}/approve-plan`, {}, { preserveScroll: true });

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left"
                onClick={() => setOpen((v) => !v)}
            >
                <span className="font-medium">{row.employee_name}</span>
                <span className="flex gap-2">
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
                    {row.status === 'draft' && (
                        <AssignPanel endpoint={`/seo-board/hod/weekly/${row.employee_id}/${currentWeekStart()}/assign-items`} templates={templates} />
                    )}
                    {row.items.map((item) => (
                        <DecideRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

type HodTab = 'today' | 'week' | 'exceptions' | 'trends' | 'history';

function hodTourSteps(setTab: (t: HodTab) => void): TourStep[] {
    return [
        {
            target: 'hod-tabs',
            onShow: () => setTab('today'),
            title: 'Five views for your team',
            text: 'Today for daily decisions, This Week for plans, Exceptions for anything blocked or rejected, Trends for scores over time, and History for the full record.',
        },
        {
            target: 'hod-today-list',
            onShow: () => setTab('today'),
            title: 'Assign and decide, right here',
            text: "Click any team member to expand their card — assign that day's extra tasks until it totals 100 points, then approve, mark late, request a fix, or reject each item they submit.",
        },
        {
            target: 'hod-week-list',
            onShow: () => setTab('week'),
            title: 'Plan the week ahead',
            text: "Build each person's weekly tasks and approve the plan before the week starts — it also has to total 100 points.",
        },
        {
            target: 'hod-exceptions',
            onShow: () => setTab('exceptions'),
            title: 'Catch problems early',
            text: 'Anyone with incomplete, late, rejected, or blocked items shows up here automatically — no need to open every card.',
        },
        {
            target: 'hod-trends',
            onShow: () => setTab('trends'),
            title: 'See the trend',
            text: 'Compare final weekly scores across your team over 4 or 12 weeks.',
        },
        {
            target: 'hod-history',
            onShow: () => setTab('history'),
            title: 'Look back on anyone, anytime',
            text: 'Pick a team member and a time range to see their full history, including evidence and your past decisions.',
        },
        {
            target: 'hod-settings-link',
            title: 'Manage templates & recipients',
            text: 'Settings is where the task library and who gets the nightly close-of-day report are managed.',
        },
    ];
}

/** The SEO Board section on "My Department" — not a standalone page, per the ease-of-use request: an HOD already has one department home. */
export function SeoHodPanel({
    department,
    employees,
    today: todayRows,
    awaitingReview,
    weekly,
    exceptions,
    teamPerformance,
    teamWeeks,
    history,
    productionTemplates,
    weeklyTemplates,
    calibrationEndsAt,
}: SeoHodPanelProps) {
    const [tab, setTab] = useState<'today' | 'week' | 'exceptions' | 'trends' | 'history'>('today');
    const inCalibration = calibrationEndsAt !== null && calibrationEndsAt >= today();
    const setTeamWeeks = (weeks: number) => reloadSeoBoard({ seo_team_weeks: weeks });

    const [historyEmployeeId, setHistoryEmployeeId] = useState(employees[0]?.id ?? 0);
    const loadHistory = (employeeId: number, period?: string) =>
        reloadSeoBoard({ seo_employee_id: employeeId, seo_period: period ?? history?.range.period ?? 'week' });

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-4 rounded-xl border p-4">
            {inCalibration && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    Calibration period until {calibrationEndsAt} — scores are visible but must not alone drive disciplinary or remuneration decisions
                    yet.
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">SEO Board — {department.name}</h2>
                <div className="flex items-center gap-3">
                    <SeoBoardTour tourKey="hod" steps={hodTourSteps(setTab)} />
                    <Link href="/seo-board/settings" className="text-sm underline" data-tour="hod-settings-link">
                        Settings
                    </Link>
                </div>
            </div>

            <div className="flex gap-1 border-b" data-tour="hod-tabs">
                {(['today', 'week', 'exceptions', 'trends', 'history'] as const).map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`px-3 py-2 text-sm font-medium ${tab === t ? 'border-primary text-primary border-b-2' : 'text-muted-foreground'}`}
                    >
                        {t === 'today'
                            ? 'Today'
                            : t === 'week'
                              ? 'This Week'
                              : t === 'exceptions'
                                ? 'Exceptions'
                                : t === 'trends'
                                  ? 'Trends'
                                  : 'History'}
                    </button>
                ))}
            </div>

            {tab === 'today' && (
                <div className="flex flex-col gap-4" data-tour="hod-today-list">
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
                            <TodayRowView key={row.card_id} row={row} templates={productionTemplates} />
                        ))}
                    </Card>
                </div>
            )}

            {tab === 'week' && (
                <Card data-tour="hod-week-list">
                    {weekly.map((row) => (
                        <WeeklyRowView key={row.card_id} row={row} templates={weeklyTemplates} />
                    ))}
                    {employees
                        .filter((e) => !weekly.some((w) => w.employee_id === e.id))
                        .map((e) => (
                            <NewWeeklyPlanRow key={e.id} employee={e} templates={weeklyTemplates} />
                        ))}
                    {weekly.length === 0 && employees.length === 0 && (
                        <p className="text-muted-foreground p-4 text-sm">No employees in this department yet.</p>
                    )}
                </Card>
            )}

            {tab === 'exceptions' && (
                <Card className="p-4" data-tour="hod-exceptions">
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
                <div className="flex flex-col gap-3" data-tour="hod-trends">
                    <div className="flex gap-1">
                        {[4, 12].map((w) => (
                            <Button key={w} size="sm" variant={teamWeeks === w ? 'default' : 'outline'} onClick={() => setTeamWeeks(w)}>
                                {w} weeks
                            </Button>
                        ))}
                    </div>
                    <Card className="overflow-x-auto p-4">
                        <p className="text-muted-foreground mb-3 text-xs">
                            Weekly final score (70% daily average + 30% weekly) per employee — one row per person, side by side, so a trend over time
                            and a comparison across the team read off the same table.
                        </p>
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
                <div className="flex flex-col gap-3" data-tour="hod-history">
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
