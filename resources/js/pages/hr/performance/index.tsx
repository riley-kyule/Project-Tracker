import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

type Cycle = {
    id: number;
    name: string;
    type: string;
    status: string;
    period_start: string;
    period_end: string;
    reviews_count: number;
};

type ReviewRow = { id: number; employee: string; reviewer: string | null; status: string; overall_rating: number | null };

type PageProps = {
    cycles: Cycle[];
    selectedCycleId: number | null;
    reviews: ReviewRow[];
    canManage: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Performance', href: '/hr/performance' }];

function label(v: string) {
    return v.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function CycleDialog() {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        type: 'annual',
        period_start: '',
        period_end: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hr/performance/cycles', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="mr-1 h-4 w-4" /> New cycle
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New review cycle</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-name">Name</Label>
                        <Input id="c-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-type">Type</Label>
                        <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                            <SelectTrigger id="c-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['annual', 'quarterly', 'probation'].map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {label(t)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-start">Period start</Label>
                            <DateField id="c-start" value={data.period_start} onChange={(v) => setData('period_start', v)} />
                            <InputError message={errors.period_start} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-end">Period end</Label>
                            <DateField id="c-end" value={data.period_end} onChange={(v) => setData('period_end', v)} />
                            <InputError message={errors.period_end} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PerformanceIndex({ cycles, selectedCycleId, reviews, canManage }: PageProps) {
    const selected = cycles.find((c) => c.id === selectedCycleId);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Performance" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Performance</h1>
                    {canManage && <CycleDialog />}
                </div>

                <div className="flex flex-wrap gap-2">
                    {cycles.map((c) => (
                        <Link
                            key={c.id}
                            href={`/hr/performance?cycle=${c.id}`}
                            preserveScroll
                            className={`rounded-md border px-3 py-1.5 text-sm ${c.id === selectedCycleId ? 'border-primary text-primary' : ''}`}
                        >
                            {c.name} <Badge variant="outline">{c.status}</Badge>
                        </Link>
                    ))}
                    {cycles.length === 0 && <p className="text-muted-foreground text-sm">No cycles yet.</p>}
                </div>

                {selected && (
                    <Card className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-sm font-semibold">{selected.name}</h2>
                                <p className="text-muted-foreground text-xs">
                                    {label(selected.type)} · {fmtDate(selected.period_start)} – {fmtDate(selected.period_end)} ·{' '}
                                    {selected.reviews_count} reviews
                                </p>
                            </div>
                            {canManage && selected.status === 'draft' && (
                                <Button
                                    size="sm"
                                    onClick={() => router.post(`/hr/performance/cycles/${selected.id}/activate`, {}, { preserveScroll: true })}
                                >
                                    Activate &amp; open reviews
                                </Button>
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="py-1 pr-3">Employee</th>
                                        <th className="py-1 pr-3">Reviewer</th>
                                        <th className="py-1 pr-3">Status</th>
                                        <th className="py-1 pr-3">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reviews.map((r) => (
                                        <tr key={r.id} className="border-t">
                                            <td className="py-1.5 pr-3">
                                                <Link href={`/hr/performance/reviews/${r.id}`} className="text-primary hover:underline">
                                                    {r.employee}
                                                </Link>
                                            </td>
                                            <td className="py-1.5 pr-3">{r.reviewer ?? '—'}</td>
                                            <td className="py-1.5 pr-3">
                                                <Badge variant="outline">{label(r.status)}</Badge>
                                            </td>
                                            <td className="py-1.5 pr-3">{r.overall_rating != null ? `${r.overall_rating}/5` : '—'}</td>
                                        </tr>
                                    ))}
                                    {reviews.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="text-muted-foreground py-4 text-center">
                                                No reviews — activate the cycle to open them.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
