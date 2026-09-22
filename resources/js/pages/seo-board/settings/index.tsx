import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Template = {
    id: number;
    card_type: string;
    section: string;
    name: string;
    classification: string;
    default_weight: number;
    min_weight: number;
    max_weight: number;
    requires_quantity: boolean;
    evidence_required: boolean;
    is_active: boolean;
};

type Hod = { id: number; name: string; email: string } | null;
type Recipient = { id: number; email: string; label: string | null; is_active: boolean };
type DepartmentRef = { id: number; name: string };

type PageProps = {
    department: DepartmentRef;
    templates: Template[];
    hod: Hod;
    recipients: Recipient[];
    can: { templates: boolean; notifications: boolean };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SEO Board Settings', href: '/seo-board/settings' }];

function TemplatesTab({ templates }: { templates: Template[] }) {
    const deactivate = (id: number) => router.delete(`/seo-board/templates/${id}`, { preserveScroll: true });
    const daily = templates.filter((t) => t.card_type === 'daily');
    const weekly = templates.filter((t) => t.card_type === 'weekly');

    const renderGroup = (label: string, items: Template[]) => (
        <Card className="p-4">
            <h2 className="mb-2 font-semibold">{label}</h2>
            <div className="flex flex-col gap-2">
                {items.map((t) => (
                    <div key={t.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 text-sm">
                        <div>
                            <div className="font-medium">{t.name}</div>
                            <div className="text-muted-foreground text-xs">
                                {t.section} · {t.classification} ·{' '}
                                {t.min_weight === t.max_weight
                                    ? `${t.default_weight} pts`
                                    : `${t.min_weight}–${t.max_weight} pts (default ${t.default_weight})`}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {t.requires_quantity && <Badge variant="outline">quantity</Badge>}
                            {t.evidence_required && <Badge variant="outline">evidence required</Badge>}
                            {!t.is_active && <Badge variant="secondary">inactive</Badge>}
                            {t.is_active && t.classification === 'production' && (
                                <Button size="sm" variant="outline" onClick={() => deactivate(t.id)}>
                                    Deactivate
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );

    return (
        <div className="flex flex-col gap-4">
            <p className="text-muted-foreground text-sm">
                Mandatory items carry the spec's fixed points and can't be reweighted here. Production-work items are a band — pick the exact weight
                when assigning them to a day's card.
            </p>
            {renderGroup('Daily card', daily)}
            {renderGroup('Weekly card', weekly)}
        </div>
    );
}

function NotificationsTab({ department, hod, recipients }: { department: DepartmentRef; hod: Hod; recipients: Recipient[] }) {
    const { data, setData, post, processing, reset, errors } = useForm({ email: '', label: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/seo-board/settings/notifications/${department.id}`, { preserveScroll: true, onSuccess: () => reset() });
    };

    const remove = (id: number) => router.delete(`/seo-board/settings/notifications-recipients/${id}`, { preserveScroll: true });

    return (
        <div className="flex flex-col gap-4">
            <Card className="p-4">
                <h2 className="mb-2 font-semibold">HOD</h2>
                {hod ? (
                    <p className="text-sm">
                        {hod.name} ({hod.email}) — the department's current manager. Change this from{' '}
                        <a href="/admin/departments" className="underline">
                            Departments
                        </a>
                        .
                    </p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No HOD is set for this department (or its parent). Set one from{' '}
                        <a href="/admin/departments" className="underline">
                            Departments
                        </a>{' '}
                        so the midnight report has somewhere to go.
                    </p>
                )}
            </Card>

            <Card className="p-4">
                <h2 className="mb-2 font-semibold">Additional recipients</h2>
                <div className="mb-3 flex flex-col gap-2">
                    {recipients.map((r) => (
                        <div key={r.id} className="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span>
                                {r.email} {r.label && <span className="text-muted-foreground">({r.label})</span>}
                            </span>
                            <span className="flex items-center gap-2">
                                {!r.is_active && <Badge variant="secondary">removed</Badge>}
                                {r.is_active && (
                                    <Button size="sm" variant="outline" onClick={() => remove(r.id)}>
                                        Remove
                                    </Button>
                                )}
                            </span>
                        </div>
                    ))}
                    {recipients.length === 0 && <p className="text-muted-foreground text-sm">No additional recipients configured.</p>}
                </div>

                <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                    <div>
                        <Label className="text-xs">Email</Label>
                        <Input
                            name="email"
                            placeholder="name@example.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="h-9 w-64"
                        />
                        {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                    </div>
                    <div>
                        <Label className="text-xs">Label (optional)</Label>
                        <Input value={data.label} onChange={(e) => setData('label', e.target.value)} className="h-9 w-48" />
                    </div>
                    <Button size="sm" disabled={processing} type="submit">
                        Add recipient
                    </Button>
                </form>
            </Card>
        </div>
    );
}

export default function SeoSettingsIndex({ department, templates, hod, recipients, can }: PageProps) {
    const [tab, setTab] = useState<'templates' | 'notifications'>(can.templates ? 'templates' : 'notifications');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SEO Board Settings" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">SEO Board Settings — {department.name}</h1>

                {can.templates && can.notifications && (
                    <div className="flex gap-1 border-b">
                        {(['templates', 'notifications'] as const).map((t) => (
                            <button
                                key={t}
                                onClick={() => setTab(t)}
                                className={`px-3 py-2 text-sm font-medium ${tab === t ? 'border-primary text-primary border-b-2' : 'text-muted-foreground'}`}
                            >
                                {t === 'templates' ? 'Templates' : 'Notifications'}
                            </button>
                        ))}
                    </div>
                )}

                {tab === 'templates' && can.templates && <TemplatesTab templates={templates} />}
                {tab === 'notifications' && can.notifications && <NotificationsTab department={department} hod={hod} recipients={recipients} />}
            </div>
        </AppLayout>
    );
}
