import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Assessment = { summary?: string; achievements?: string; challenges?: string; strengths?: string; development?: string };

type Review = {
    id: number;
    employee: string;
    job_title: string | null;
    reviewer: string | null;
    cycle: string;
    status: string;
    self_assessment: Assessment;
    manager_assessment: Assessment;
    overall_rating: number | null;
    submitted_at: string | null;
    shared_at: string | null;
    acknowledged_at: string | null;
};

type PageProps = {
    review: Review;
    can: { selfAssess: boolean; managerAssess: boolean; submitSelf: boolean; share: boolean; acknowledge: boolean };
};

function label(v: string) {
    return v.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function PerformanceReviewShow({ review, can }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Performance', href: '/hr/performance' },
        { title: `${review.employee} — ${review.cycle}`, href: `/hr/performance/reviews/${review.id}` },
    ];

    const [self, setSelf] = useState<Assessment>(review.self_assessment ?? {});
    const [mgr, setMgr] = useState<Assessment>(review.manager_assessment ?? {});
    const [rating, setRating] = useState<string>(review.overall_rating != null ? String(review.overall_rating) : '');

    const saveSelf = () => router.patch(`/hr/performance/reviews/${review.id}`, { self_assessment: self }, { preserveScroll: true });
    const saveMgr = () =>
        router.patch(
            `/hr/performance/reviews/${review.id}`,
            { manager_assessment: mgr, overall_rating: rating === '' ? null : Number(rating) },
            { preserveScroll: true },
        );
    const transition = (to: string) => router.post(`/hr/performance/reviews/${review.id}/transition`, { to }, { preserveScroll: true });

    const Area = ({
        label: l,
        value,
        onChange,
        disabled,
    }: {
        label: string;
        value: string | undefined;
        onChange: (v: string) => void;
        disabled: boolean;
    }) => (
        <div className="grid gap-1.5">
            <Label>{l}</Label>
            <textarea
                className="border-input bg-background min-h-20 rounded-md border p-2 text-sm disabled:opacity-70"
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
            />
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Review — ${review.employee}`} />
            <div className="flex max-w-2xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{review.employee}</h1>
                    <p className="text-muted-foreground text-sm">
                        {review.job_title} · {review.cycle} · reviewer {review.reviewer ?? '—'}
                    </p>
                    <Badge className="mt-1">{label(review.status)}</Badge>
                </div>

                <Card className="grid gap-3 p-4">
                    <h2 className="text-sm font-semibold">Self assessment</h2>
                    <Area label="Summary" value={self.summary} onChange={(v) => setSelf({ ...self, summary: v })} disabled={!can.selfAssess} />
                    <Area
                        label="Key achievements"
                        value={self.achievements}
                        onChange={(v) => setSelf({ ...self, achievements: v })}
                        disabled={!can.selfAssess}
                    />
                    <Area
                        label="Challenges"
                        value={self.challenges}
                        onChange={(v) => setSelf({ ...self, challenges: v })}
                        disabled={!can.selfAssess}
                    />
                    {can.selfAssess && (
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" onClick={saveSelf}>
                                Save
                            </Button>
                            {can.submitSelf && (
                                <Button size="sm" onClick={() => transition('submit_self')}>
                                    Submit to reviewer
                                </Button>
                            )}
                        </div>
                    )}
                </Card>

                <Card className="grid gap-3 p-4">
                    <h2 className="text-sm font-semibold">Manager assessment</h2>
                    <Area label="Summary" value={mgr.summary} onChange={(v) => setMgr({ ...mgr, summary: v })} disabled={!can.managerAssess} />
                    <Area label="Strengths" value={mgr.strengths} onChange={(v) => setMgr({ ...mgr, strengths: v })} disabled={!can.managerAssess} />
                    <Area
                        label="Development areas"
                        value={mgr.development}
                        onChange={(v) => setMgr({ ...mgr, development: v })}
                        disabled={!can.managerAssess}
                    />
                    <div className="grid w-32 gap-1.5">
                        <Label>Overall rating (1–5)</Label>
                        <Input
                            type="number"
                            step="0.5"
                            min={1}
                            max={5}
                            value={rating}
                            onChange={(e) => setRating(e.target.value)}
                            disabled={!can.managerAssess}
                        />
                    </div>
                    {can.managerAssess && (
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" onClick={saveMgr}>
                                Save
                            </Button>
                            {can.share && (
                                <Button size="sm" onClick={() => transition('share')}>
                                    Share with employee
                                </Button>
                            )}
                        </div>
                    )}
                </Card>

                {can.acknowledge && (
                    <div>
                        <Button onClick={() => transition('acknowledge')}>Acknowledge review</Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
