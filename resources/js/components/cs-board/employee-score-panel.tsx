import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fmtDate } from '@/lib/utils';
import { router } from '@inertiajs/react';
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
    completion_criteria: string | null;
    evidence_required: boolean;
    employee_status: string;
    employee_comment: string | null;
    hod_decision: string | null;
    earned_points: number | null;
    evidence: Evidence[];
};

type Card_ = {
    id: number;
    work_date?: string;
    week_start_date?: string;
    week_end_date?: string;
    status: string;
    planned_points: number;
    employee_submitted_points?: number | null;
    approved_points: number | null;
    can_update: boolean;
    items: Item[];
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

type HistorySummary = {
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
    cards: {
        card_id: number;
        work_date: string;
        status: string;
        approved_points: number | null;
        employee_submitted_points: number | null;
        quota_percentage: number | null;
        items: Item[];
    }[];
    weekly_final_scores: {
        week_start_date: string;
        avg_daily_approved_score: number | null;
        weekly_approved_score: number | null;
        final_score: number | null;
        is_final: boolean;
    }[];
};

type Interaction = {
    id: number;
    customer_identifier: string | null;
    channel: string;
    enquiry_received_at: string;
    first_response_at: string | null;
    response_standard_minutes: number;
    resolution_status: string;
};

export type CsEmployeeScoreBoardPayload = {
    dailyCard: Card_ | null;
    weeklyCard: Card_ | null;
    commercial: Commercial;
    employeeId: number;
    interactions: Interaction[];
    history: HistorySummary;
    range: { from: string; to: string; period: string };
};

const STATUS_OPTIONS = ['not_started', 'in_progress', 'submitted', 'blocked'];
const SALE_CATEGORIES = [
    { value: 'new', label: 'New customer' },
    { value: 'renewal', label: 'Renewal' },
    { value: 'reactivation', label: 'Reactivation' },
];

function ItemRow({ item, canUpdate }: { item: Item; canUpdate: boolean }) {
    const [status, setStatus] = useState(item.employee_status);
    const [comment, setComment] = useState(item.employee_comment ?? '');
    const [quantity, setQuantity] = useState(item.achieved_quantity?.toString() ?? '');
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const isAutoCalculated = item.metric_type !== null;

    const save = () => {
        setProcessing(true);
        router.post(
            `/cs-board/items/${item.id}/status`,
            { employee_status: status, employee_comment: comment, achieved_quantity: isAutoCalculated ? undefined : quantity || undefined },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    const uploadEvidence = () => {
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        router.post(`/cs-board/items/${item.id}/evidence`, form, { preserveScroll: true, onFinish: () => setFile(null) });
    };

    return (
        <div className="flex flex-col gap-2 border-b py-3 last:border-b-0">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div className="font-medium">{item.name}</div>
                    {item.completion_criteria && <div className="text-muted-foreground text-xs">{item.completion_criteria}</div>}
                    {isAutoCalculated && (
                        <div className="text-muted-foreground text-xs">
                            {item.metric_type === 'service_quality'
                                ? `Service quality achievement: ${item.achieved_quantity ?? 0}% (auto-calculated from response time, resolution and complaints)`
                                : `Commercial achievement: ${item.achieved_quantity ?? 0}% of target (auto-calculated from cleared sales — see the Sales tab)`}
                        </div>
                    )}
                </div>
                <div className="flex items-center gap-2 text-sm">
                    {item.due_time && <span className="text-muted-foreground">due {item.due_time}</span>}
                    <span className="text-muted-foreground">weight {item.weight}</span>
                    {item.hod_decision && <Badge variant="outline">{item.hod_decision}</Badge>}
                    {item.earned_points !== null && <Badge>{item.earned_points} pts</Badge>}
                </div>
            </div>

            {canUpdate && (
                <div className="flex flex-wrap items-end gap-2">
                    <div>
                        <Label className="text-xs">Status</Label>
                        <select
                            className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            {STATUS_OPTIONS.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                    </div>
                    {!isAutoCalculated && item.target_quantity !== null && (
                        <div>
                            <Label className="text-xs">
                                Achieved ({item.quantity_unit ?? 'units'}) / {item.target_quantity}
                            </Label>
                            <Input className="h-8 w-24" value={quantity} onChange={(e) => setQuantity(e.target.value)} />
                        </div>
                    )}
                    <div className="grow">
                        <Label className="text-xs">Comment</Label>
                        <Input className="h-8" value={comment} onChange={(e) => setComment(e.target.value)} />
                    </div>
                    <Button size="sm" disabled={processing} onClick={save}>
                        Save
                    </Button>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {item.evidence.map((e) => (
                    <a key={e.id} href={`/attachments/${e.id}`} className="text-xs underline">
                        {e.original_name}
                    </a>
                ))}
                <div className="flex items-center gap-1">
                    <input type="file" className="text-xs" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                    <Button size="sm" variant="outline" disabled={!file} onClick={uploadEvidence}>
                        Attach evidence
                    </Button>
                </div>
                {item.evidence_required && item.evidence.length === 0 && <Badge variant="destructive">evidence required</Badge>}
            </div>
        </div>
    );
}

function CardPanel({ card, emptyMessage }: { card: Card_ | null; emptyMessage: string }) {
    if (card === null) {
        return <p className="text-muted-foreground p-4 text-sm">{emptyMessage}</p>;
    }

    const grouped = card.items.reduce<Record<string, Item[]>>((acc, item) => {
        (acc[item.section] ??= []).push(item);
        return acc;
    }, {});

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{card.status}</Badge>
                <Badge variant="outline">planned {card.planned_points}</Badge>
                {card.employee_submitted_points !== undefined && <Badge variant="outline">submitted {card.employee_submitted_points ?? '—'}</Badge>}
                <Badge>{card.approved_points ?? 'pending approval'}</Badge>
            </div>
            {Object.entries(grouped).map(([section, items]) => (
                <Card key={section} className="p-4">
                    <h3 className="mb-2 font-semibold capitalize">{section.replace(/_/g, ' ')}</h3>
                    {items.map((item) => (
                        <ItemRow key={item.id} item={item} canUpdate={card.can_update} />
                    ))}
                </Card>
            ))}
        </div>
    );
}

function CommercialCard({ commercial }: { commercial: Commercial }) {
    const rows = [
        {
            label: 'New paying customers',
            count: commercial.new_customers,
            countTarget: commercial.new_customers_target,
            revenue: commercial.new_customer_revenue,
            revenueTarget: commercial.new_customer_revenue_target,
        },
        {
            label: 'Renewed / reactivated customers',
            count: commercial.renewed_customers,
            countTarget: commercial.renewed_customers_target,
            revenue: commercial.retained_revenue,
            revenueTarget: commercial.retained_revenue_target,
        },
    ];

    return (
        <Card className="p-4">
            <h3 className="mb-1 font-semibold">This week's commercial targets</h3>
            <p className="text-muted-foreground mb-3 text-xs">Week of {fmtDate(commercial.week_start_date)} · set by your HOD</p>
            <div className="grid gap-3 sm:grid-cols-2">
                {rows.map((r) => (
                    <div key={r.label} className="rounded-md border p-3">
                        <div className="text-sm font-medium">{r.label}</div>
                        <div className="mt-1 flex justify-between text-sm">
                            <span>
                                {r.count} / {r.countTarget} customers
                            </span>
                            <span>
                                {commercial.currency} {r.revenue.toLocaleString()} / {r.revenueTarget.toLocaleString()}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}

function SalesForm({ employeeId, weekStart }: { employeeId: number; weekStart: string }) {
    const [category, setCategory] = useState('new');
    const [customerIdentifier, setCustomerIdentifier] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const [amount, setAmount] = useState('');
    const [currency, setCurrency] = useState('KES');
    const [contactChannel, setContactChannel] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            `/cs-board/employees/${employeeId}/sales`,
            {
                category,
                customer_identifier: customerIdentifier,
                payment_reference: paymentReference,
                amount,
                currency,
                contact_channel: contactChannel || undefined,
                week_start_date: weekStart,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setCustomerIdentifier('');
                    setPaymentReference('');
                    setAmount('');
                },
            },
        );
    };

    return (
        <Card className="flex flex-col gap-3 p-4">
            <h3 className="font-semibold">Record a sale</h3>
            <p className="text-muted-foreground text-xs">
                Logging a sale does not score it. Your HOD reviews the payment reference and evidence, then clears it before it counts toward your
                weekly target.
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
                <div>
                    <Label className="text-xs">Category</Label>
                    <select
                        className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    >
                        {SALE_CATEGORIES.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <Label className="text-xs">Customer identifier</Label>
                    <Input className="h-8" value={customerIdentifier} onChange={(e) => setCustomerIdentifier(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Contact channel</Label>
                    <Input
                        className="h-8"
                        value={contactChannel}
                        onChange={(e) => setContactChannel(e.target.value)}
                        placeholder="chat, call, email"
                    />
                </div>
                <div>
                    <Label className="text-xs">Payment reference</Label>
                    <Input className="h-8" value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Amount</Label>
                    <Input className="h-8" value={amount} onChange={(e) => setAmount(e.target.value)} />
                </div>
                <div>
                    <Label className="text-xs">Currency</Label>
                    <Input className="h-8" value={currency} onChange={(e) => setCurrency(e.target.value.toUpperCase())} maxLength={3} />
                </div>
            </div>
            <Button size="sm" disabled={processing || !customerIdentifier || !paymentReference || !amount} onClick={submit} className="self-start">
                Record sale
            </Button>
        </Card>
    );
}

const RESOLUTION_OPTIONS = [
    { value: 'first_contact_resolution', label: 'Resolved on first contact' },
    { value: 'resolved_after_escalation', label: 'Resolved after escalation' },
    { value: 'pending_customer', label: 'Pending customer' },
    { value: 'pending_internal_owner', label: 'Pending internal owner' },
    { value: 'unresolved', label: 'Unresolved' },
];

function InteractionRow({ interaction }: { interaction: Interaction }) {
    const [resolution, setResolution] = useState(interaction.resolution_status);
    const [processing, setProcessing] = useState(false);

    const minutesWaiting = interaction.first_response_at
        ? null
        : Math.round((Date.now() - new Date(interaction.enquiry_received_at).getTime()) / 60000);
    const overdue = minutesWaiting !== null && minutesWaiting > interaction.response_standard_minutes;

    const markResponded = () => {
        setProcessing(true);
        router.post(`/cs-board/interactions/${interaction.id}/respond`, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const saveResolution = () => {
        setProcessing(true);
        router.post(
            `/cs-board/interactions/${interaction.id}/resolve`,
            { resolution_status: resolution },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b p-2 text-sm last:border-b-0">
            <div>
                <span className="font-medium">{interaction.customer_identifier ?? 'unnamed customer'}</span> — {interaction.channel}
                <div className="text-muted-foreground text-xs">
                    received {fmtDate(interaction.enquiry_received_at)} · standard {interaction.response_standard_minutes}min
                    {overdue && (
                        <Badge variant="destructive" className="ml-1">
                            overdue
                        </Badge>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-2">
                {!interaction.first_response_at ? (
                    <Button size="sm" variant="outline" disabled={processing} onClick={markResponded}>
                        Mark first response now
                    </Button>
                ) : (
                    <Badge variant="outline">responded</Badge>
                )}
                <select
                    className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
                    value={resolution}
                    onChange={(e) => setResolution(e.target.value)}
                >
                    {RESOLUTION_OPTIONS.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <Button size="sm" disabled={processing || resolution === interaction.resolution_status} onClick={saveResolution}>
                    Save
                </Button>
            </div>
        </div>
    );
}

function LogInteractionForm({ employeeId }: { employeeId: number }) {
    const [customerIdentifier, setCustomerIdentifier] = useState('');
    const [channel, setChannel] = useState('chat');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            `/cs-board/employees/${employeeId}/interactions`,
            { customer_identifier: customerIdentifier || undefined, channel },
            { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: () => setCustomerIdentifier('') },
        );
    };

    return (
        <Card className="flex flex-wrap items-end gap-2 p-3">
            <div>
                <Label className="text-xs">Customer</Label>
                <Input className="h-8 w-48" value={customerIdentifier} onChange={(e) => setCustomerIdentifier(e.target.value)} />
            </div>
            <div>
                <Label className="text-xs">Channel</Label>
                <select
                    className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
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
            <Button size="sm" disabled={processing} onClick={submit}>
                Log enquiry received now
            </Button>
        </Card>
    );
}

function ServiceTab({ employeeId, interactions }: { employeeId: number; interactions: Interaction[] }) {
    return (
        <div className="flex flex-col gap-4">
            <p className="text-muted-foreground text-sm">
                Log each enquiry when it comes in, mark when you first respond, and set its resolution. This feeds your weekly "Customer-service
                quality" score automatically — see the Week tab.
            </p>
            <LogInteractionForm employeeId={employeeId} />
            <Card>
                {interactions.length === 0 && <p className="text-muted-foreground p-4 text-sm">No enquiries logged today yet.</p>}
                {interactions.map((i) => (
                    <InteractionRow key={i.id} interaction={i} />
                ))}
            </Card>
        </div>
    );
}

/** A past day, expandable to see exactly what was recorded — read-only. */
function HistoryCardRow({ card }: { card: HistorySummary['cards'][number] }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                className="hover:bg-accent flex w-full items-center justify-between gap-2 py-1.5 text-left text-sm"
                onClick={() => setOpen((v) => !v)}
            >
                <span>{fmtDate(card.work_date)}</span>
                <span className="flex gap-2">
                    <Badge variant="outline">{card.status}</Badge>
                    <Badge>{card.approved_points ?? card.employee_submitted_points ?? '—'}</Badge>
                </span>
            </button>
            {open && (
                <div className="flex flex-col gap-2 pb-3">
                    {card.items.map((item) => (
                        <div key={item.id} className="rounded-md border p-2 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">{item.name}</span>
                                <span className="flex gap-2">
                                    <span className="text-muted-foreground">weight {item.weight}</span>
                                    {item.hod_decision && <Badge variant="outline">{item.hod_decision}</Badge>}
                                    {item.earned_points !== null && <Badge>{item.earned_points} pts</Badge>}
                                </span>
                            </div>
                            <div className="text-muted-foreground text-xs">{item.employee_status}</div>
                        </div>
                    ))}
                    {card.items.length === 0 && <p className="text-muted-foreground text-sm">No items recorded.</p>}
                </div>
            )}
        </div>
    );
}

function HistoryPanel({ history, range }: { history: HistorySummary; range: CsEmployeeScoreBoardPayload['range'] }) {
    // Reloads only the scoreBoard prop on whatever board page this panel is embedded in.
    const setPeriod = (period: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set('period', period);
        router.get(window.location.pathname, Object.fromEntries(params), { preserveState: true, preserveScroll: true, only: ['scoreBoard'] });
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex gap-1">
                {['week', 'month', 'quarter'].map((p) => (
                    <Button key={p} size="sm" variant={range.period === p ? 'default' : 'outline'} onClick={() => setPeriod(p)}>
                        {p}
                    </Button>
                ))}
            </div>
            <Card className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
                <div>
                    <div className="text-muted-foreground text-xs">On-time rate</div>
                    <div className="text-lg font-semibold">{history.on_time_rate === null ? '—' : `${history.on_time_rate}%`}</div>
                    <div className="text-muted-foreground text-xs">
                        {history.on_time_count} of {history.decided_count}
                    </div>
                </div>
                <div>
                    <div className="text-muted-foreground text-xs">Correction rate</div>
                    <div className="text-lg font-semibold">{history.correction_rate === null ? '—' : `${history.correction_rate}%`}</div>
                    <div className="text-muted-foreground text-xs">
                        {history.correction_count} of {history.decided_count}
                    </div>
                </div>
                <div>
                    <div className="text-muted-foreground text-xs">Rejection rate</div>
                    <div className="text-lg font-semibold">{history.rejection_rate === null ? '—' : `${history.rejection_rate}%`}</div>
                    <div className="text-muted-foreground text-xs">
                        {history.rejection_count} of {history.decided_count}
                    </div>
                </div>
                <div>
                    <div className="text-muted-foreground text-xs">Blocked points</div>
                    <div className="text-lg font-semibold">{history.blocked_points}</div>
                </div>
                <div>
                    <div className="text-muted-foreground text-xs">Missing evidence</div>
                    <div className="text-lg font-semibold">{history.missing_evidence_count}</div>
                </div>
            </Card>
            <Card className="p-4">
                <h3 className="mb-2 font-semibold">Commercial achievement by week</h3>
                <div className="flex flex-col gap-2">
                    {history.commercial.map((c) => (
                        <div key={c.week_start_date} className="rounded-md border p-2 text-sm">
                            <div className="mb-1 font-medium">week of {fmtDate(c.week_start_date)}</div>
                            <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                <span>
                                    New: {c.new_customers}/{c.new_customers_target} · {c.currency} {c.new_customer_revenue.toLocaleString()}/
                                    {c.new_customer_revenue_target.toLocaleString()}
                                </span>
                                <span>
                                    Renewed: {c.renewed_customers}/{c.renewed_customers_target} · {c.currency} {c.retained_revenue.toLocaleString()}/
                                    {c.retained_revenue_target.toLocaleString()}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
            <Card className="p-4">
                <h3 className="mb-2 font-semibold">Daily cards</h3>
                <p className="text-muted-foreground mb-2 text-xs">Click a day to see its items.</p>
                <div className="flex flex-col">
                    {history.cards.map((c) => (
                        <HistoryCardRow key={c.card_id} card={c} />
                    ))}
                    {history.cards.length === 0 && <p className="text-muted-foreground text-sm">No cards in this range.</p>}
                </div>
            </Card>
            <Card className="p-4">
                <h3 className="mb-2 font-semibold">Weekly final scores (70% daily + 30% weekly)</h3>
                <div className="flex flex-col gap-1">
                    {history.weekly_final_scores.map((s) => (
                        <div key={s.week_start_date} className="flex items-center justify-between border-b py-1 text-sm last:border-b-0">
                            <span>week of {fmtDate(s.week_start_date)}</span>
                            <Badge variant={s.is_final ? 'default' : 'secondary'}>{s.final_score ?? '—'}</Badge>
                        </div>
                    ))}
                    {history.weekly_final_scores.length === 0 && <p className="text-muted-foreground text-sm">No finalised weeks in this range.</p>}
                </div>
            </Card>
        </div>
    );
}

export function CsEmployeeScorePanel({ dailyCard, weeklyCard, commercial, employeeId, interactions, history, range }: CsEmployeeScoreBoardPayload) {
    const [tab, setTab] = useState<'today' | 'week' | 'service' | 'sales' | 'history'>('today');

    return (
        <div className="flex flex-col gap-4">
            <div className="flex gap-1 border-b">
                {(['today', 'week', 'service', 'sales', 'history'] as const).map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`px-3 py-2 text-sm font-medium ${tab === t ? 'border-primary text-primary border-b-2' : 'text-muted-foreground'}`}
                    >
                        {t === 'today' ? 'Today' : t === 'week' ? 'This Week' : t === 'service' ? 'Service' : t === 'sales' ? 'Sales' : 'History'}
                    </button>
                ))}
            </div>

            {tab === 'today' && <CardPanel card={dailyCard} emptyMessage="No daily card yet — check back tomorrow or ask your HOD." />}
            {tab === 'week' && (
                <div className="flex flex-col gap-4">
                    <CommercialCard commercial={commercial} />
                    <CardPanel card={weeklyCard} emptyMessage="No weekly card yet — ask your HOD to set this week's plan." />
                </div>
            )}
            {tab === 'service' && <ServiceTab employeeId={employeeId} interactions={interactions} />}
            {tab === 'sales' && <SalesForm employeeId={employeeId} weekStart={commercial.week_start_date} />}
            {tab === 'history' && <HistoryPanel history={history} range={range} />}
        </div>
    );
}
