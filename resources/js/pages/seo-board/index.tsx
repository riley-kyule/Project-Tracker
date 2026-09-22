import { SeoBoardTour, type TourStep } from '@/components/seo-board/tour';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
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

type PageProps = {
    dailyCard: Card_ | null;
    weeklyCard: Card_ | null;
    history: HistorySummary;
    range: { from: string; to: string; period: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My SEO Board', href: '/seo-board' }];
const STATUS_OPTIONS = ['not_started', 'in_progress', 'submitted', 'blocked'];

function ItemRow({ item, canUpdate }: { item: Item; canUpdate: boolean }) {
    const [status, setStatus] = useState(item.employee_status);
    const [comment, setComment] = useState(item.employee_comment ?? '');
    const [quantity, setQuantity] = useState(item.achieved_quantity?.toString() ?? '');
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);

    const save = () => {
        setProcessing(true);
        router.post(
            `/seo-board/items/${item.id}/status`,
            { employee_status: status, employee_comment: comment, achieved_quantity: quantity || undefined },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    const uploadEvidence = () => {
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        router.post(`/seo-board/items/${item.id}/evidence`, form, { preserveScroll: true, onFinish: () => setFile(null) });
    };

    return (
        <div className="flex flex-col gap-2 border-b py-3 last:border-b-0">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div className="font-medium">{item.name}</div>
                    {item.completion_criteria && <div className="text-muted-foreground text-xs">{item.completion_criteria}</div>}
                    {item.assigned_url && (
                        <a href={item.assigned_url} target="_blank" rel="noreferrer" className="text-xs break-all underline">
                            {item.assigned_url}
                        </a>
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
                <div className="flex flex-wrap items-end gap-2" data-tour="emp-status">
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
                    {item.target_quantity !== null && (
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

            <div className="flex flex-wrap items-center gap-2" data-tour="emp-evidence">
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
        <div className="flex flex-col gap-4" data-tour="emp-items">
            <div className="flex flex-wrap items-center gap-2" data-tour="emp-points">
                <Badge variant="outline">{card.status}</Badge>
                <Badge variant="outline">planned {card.planned_points}</Badge>
                {card.employee_submitted_points !== undefined && <Badge variant="outline">submitted {card.employee_submitted_points ?? '—'}</Badge>}
                <Badge>{card.approved_points ?? 'pending approval'}</Badge>
            </div>
            {Object.entries(grouped).map(([section, items]) => (
                <Card key={section} className="p-4">
                    <h3 className="mb-2 font-semibold capitalize">{section}</h3>
                    {items.map((item) => (
                        <ItemRow key={item.id} item={item} canUpdate={card.can_update} />
                    ))}
                </Card>
            ))}
        </div>
    );
}

/** A past day, expandable to see exactly what was recorded — read-only, since only the HOD's decision (visible here) ever changes it, not the employee viewing their own history. */
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
                            {item.evidence.length > 0 && (
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {item.evidence.map((e) => (
                                        <a key={e.id} href={`/attachments/${e.id}`} className="text-xs underline">
                                            {e.original_name}
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                    {card.items.length === 0 && <p className="text-muted-foreground text-sm">No items recorded.</p>}
                </div>
            )}
        </div>
    );
}

function HistoryPanel({ history, range }: { history: HistorySummary; range: PageProps['range'] }) {
    const setPeriod = (period: string) => router.get('/seo-board', { period }, { preserveState: true, only: ['history', 'range'] });

    return (
        <div className="flex flex-col gap-4">
            <div className="flex gap-1" data-tour="emp-history-range">
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
                <h3 className="mb-2 font-semibold">Daily cards</h3>
                <p className="text-muted-foreground mb-2 text-xs">Click a day to see its items and evidence.</p>
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

export default function SeoBoardIndex({ dailyCard, weeklyCard, history, range }: PageProps) {
    const [tab, setTab] = useState<'today' | 'week' | 'history'>('today');

    const tourSteps: TourStep[] = [
        {
            target: 'emp-tabs',
            onShow: () => setTab('today'),
            title: 'Three tabs, one page',
            text: 'Today is your daily checklist, This Week is your weekly plan, and History has scores your manager has already approved.',
        },
        {
            target: 'emp-points',
            onShow: () => setTab('today'),
            title: 'Your points at a glance',
            text: "Planned is always 100. Submitted is what you've marked done. Approved only appears once your manager reviews it — submitting isn't scoring.",
        },
        {
            target: 'emp-items',
            onShow: () => setTab('today'),
            title: "Today's checklist",
            text: 'Grouped by section. Monitoring, implementation and documentation are added automatically every day — your manager fills in the rest each morning.',
        },
        {
            target: 'emp-status',
            onShow: () => setTab('today'),
            title: 'Update as you work',
            text: 'Move a task to In progress, then Submitted when it’s ready — or Blocked with a reason if something outside your control is stopping you.',
        },
        {
            target: 'emp-evidence',
            onShow: () => setTab('today'),
            title: 'Attach your proof',
            text: 'Some tasks need a screenshot, document, or link attached before you can submit them.',
        },
        {
            target: 'emp-history-range',
            onShow: () => setTab('history'),
            title: 'Check your history anytime',
            text: 'Switch here to see approved scores from past days and weeks — click any day to see exactly what was recorded.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My SEO Board" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">My SEO Board</h1>
                    <SeoBoardTour tourKey="employee" steps={tourSteps} />
                </div>

                <div className="flex gap-1 border-b" data-tour="emp-tabs">
                    {(['today', 'week', 'history'] as const).map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`px-3 py-2 text-sm font-medium ${tab === t ? 'border-primary text-primary border-b-2' : 'text-muted-foreground'}`}
                        >
                            {t === 'today' ? 'Today' : t === 'week' ? 'This Week' : 'History'}
                        </button>
                    ))}
                </div>

                {tab === 'today' && <CardPanel card={dailyCard} emptyMessage="No daily card yet — check back tomorrow or ask your HOD." />}
                {tab === 'week' && <CardPanel card={weeklyCard} emptyMessage="No weekly card yet — ask your HOD to set this week's plan." />}
                {tab === 'history' && <HistoryPanel history={history} range={range} />}
            </div>
        </AppLayout>
    );
}
