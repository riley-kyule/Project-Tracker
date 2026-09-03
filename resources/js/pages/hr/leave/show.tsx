import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDate, fmtDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Approval = { approver: string | null; action: string; note: string | null; acted_at: string };

type LeaveRequest = {
    id: number;
    employee: string;
    type: string;
    code: string;
    status: string;
    days: number;
    start_date: string;
    end_date: string;
    is_emergency: boolean;
    reason: string | null;
    contact_during_leave: string | null;
    handover_to: string | null;
    decision_note: string | null;
    overlap_override_reason: string | null;
    approvals: Approval[];
};

type PageProps = { request: LeaveRequest; canDecide: boolean; canCancel: boolean };

function Field({ label: l, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-muted-foreground text-xs">{l}</span>
            <span className="text-sm">{value ?? '—'}</span>
        </div>
    );
}

export default function LeaveRequestShow({ request, canDecide, canCancel }: PageProps) {
    const [note, setNote] = useState('');
    const [override, setOverride] = useState('');
    const [busy, setBusy] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leave', href: '/hr/leave' },
        { title: `Request #${request.id}`, href: `/hr/leave/requests/${request.id}` },
    ];

    const decide = (approve: boolean) => {
        setBusy(true);
        router.post(
            `/hr/leave/requests/${request.id}/decision`,
            { approve, note, override_reason: override },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Leave request #${request.id}`} />
            <div className="flex max-w-2xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        {request.employee} — {request.type}
                    </h1>
                    <div className="mt-1 flex items-center gap-2">
                        <Badge>{request.status}</Badge>
                        {request.is_emergency && <Badge variant="secondary">emergency</Badge>}
                    </div>
                </div>

                <Card className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                    <Field label="Dates" value={`${fmtDate(request.start_date)} → ${fmtDate(request.end_date)}`} />
                    <Field label="Working days" value={request.days} />
                    <Field label="Reason" value={request.reason} />
                    <Field label="Contact while away" value={request.contact_during_leave} />
                    <Field label="Handover to" value={request.handover_to} />
                    <Field label="Decision note" value={request.decision_note} />
                    {request.overlap_override_reason && <Field label="Overlap override" value={request.overlap_override_reason} />}
                </Card>

                {request.approvals.length > 0 && (
                    <Card className="p-4">
                        <h2 className="mb-2 text-sm font-semibold">Approval trail</h2>
                        {request.approvals.map((a, i) => (
                            <div key={i} className="border-t py-1.5 text-sm first:border-0">
                                <span className="font-medium">{a.approver ?? 'Unknown'}</span> {a.action}
                                {a.note ? ` — ${a.note}` : ''}
                                <span className="text-muted-foreground ml-2 text-xs">{fmtDateTime(a.acted_at)}</span>
                            </div>
                        ))}
                    </Card>
                )}

                {canDecide && request.status === 'pending' && (
                    <Card className="grid gap-3 p-4">
                        <h2 className="text-sm font-semibold">Decision</h2>
                        <div className="grid gap-1.5">
                            <Label htmlFor="note">Note (optional)</Label>
                            <Input id="note" value={note} onChange={(e) => setNote(e.target.value)} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ovr">Overlap override reason (if a colleague is already off)</Label>
                            <Input id="ovr" value={override} onChange={(e) => setOverride(e.target.value)} />
                        </div>
                        <div className="flex gap-2">
                            <Button disabled={busy} onClick={() => decide(true)}>
                                Approve
                            </Button>
                            <Button variant="outline" disabled={busy} onClick={() => decide(false)}>
                                Reject
                            </Button>
                        </div>
                    </Card>
                )}

                {canCancel && ['pending', 'approved'].includes(request.status) && (
                    <div>
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (confirm('Cancel this leave request?'))
                                    router.post(`/hr/leave/requests/${request.id}/cancel`, {}, { preserveScroll: true });
                            }}
                        >
                            Cancel request
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
