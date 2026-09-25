import { SeoBoardTour, type TourStep } from '@/components/seo-board/tour';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Template = {
    id: number;
    card_type: string;
    section: string;
    name: string;
    description: string | null;
    classification: string;
    default_weight: number;
    min_weight: number;
    max_weight: number;
    requires_quantity: boolean;
    quantity_unit: string | null;
    evidence_type: string | null;
    evidence_required: boolean;
    completion_criteria: string | null;
    is_active: boolean;
    position: number | null;
};

const CLASSIFICATIONS = ['mandatory', 'production', 'scheduled', 'conditional', 'additional'];

type TemplateFormValues = {
    card_type: string;
    section: string;
    name: string;
    description: string;
    classification: string;
    default_weight: string;
    min_weight: string;
    max_weight: string;
    requires_quantity: boolean;
    quantity_unit: string;
    evidence_type: string;
    evidence_required: boolean;
    completion_criteria: string;
    is_active: boolean;
    [key: string]: string | boolean;
};

function blankTemplate(cardType: string, classification: string): TemplateFormValues {
    return {
        card_type: cardType,
        section: classification === 'production' ? 'production' : '',
        name: '',
        description: '',
        classification,
        default_weight: '10',
        min_weight: '5',
        max_weight: '20',
        requires_quantity: classification === 'production',
        quantity_unit: '',
        evidence_type: '',
        evidence_required: true,
        completion_criteria: '',
        is_active: true,
    };
}

function templateToFormValues(t: Template): TemplateFormValues {
    return {
        card_type: t.card_type,
        section: t.section,
        name: t.name,
        description: t.description ?? '',
        classification: t.classification,
        default_weight: String(t.default_weight),
        min_weight: String(t.min_weight),
        max_weight: String(t.max_weight),
        requires_quantity: t.requires_quantity,
        quantity_unit: t.quantity_unit ?? '',
        evidence_type: t.evidence_type ?? '',
        evidence_required: t.evidence_required,
        completion_criteria: t.completion_criteria ?? '',
        is_active: t.is_active,
    };
}

/** Shared by "Add template" and "Edit" — the backend already supported both, only the UI for it was missing. */
function TemplateFormDialog({
    trigger,
    title,
    initial,
    url,
    method,
}: {
    trigger: React.ReactNode;
    title: string;
    initial: TemplateFormValues;
    url: string;
    method: 'post' | 'patch';
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset } = useForm<TemplateFormValues>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        };
        if (method === 'post') {
            post(url, options);
        } else {
            patch(url, options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) setData(initial);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label className="text-xs">Card type</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                                value={data.card_type}
                                onChange={(e) => setData('card_type', e.target.value)}
                            >
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                        <div>
                            <Label className="text-xs">Classification</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                                value={data.classification}
                                onChange={(e) => setData('classification', e.target.value)}
                            >
                                {CLASSIFICATIONS.map((c) => (
                                    <option key={c} value={c}>
                                        {c}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label className="text-xs">Name</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                    </div>
                    <div>
                        <Label className="text-xs">Section</Label>
                        <Input value={data.section} onChange={(e) => setData('section', e.target.value)} placeholder="e.g. monitoring, production" />
                        {errors.section && <p className="text-destructive text-xs">{errors.section}</p>}
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <Label className="text-xs">Default weight</Label>
                            <Input type="number" step="0.5" value={data.default_weight} onChange={(e) => setData('default_weight', e.target.value)} />
                        </div>
                        <div>
                            <Label className="text-xs">Min weight</Label>
                            <Input type="number" step="0.5" value={data.min_weight} onChange={(e) => setData('min_weight', e.target.value)} />
                        </div>
                        <div>
                            <Label className="text-xs">Max weight</Label>
                            <Input type="number" step="0.5" value={data.max_weight} onChange={(e) => setData('max_weight', e.target.value)} />
                        </div>
                    </div>
                    {(errors.default_weight || errors.min_weight || errors.max_weight) && (
                        <p className="text-destructive text-xs">{errors.default_weight ?? errors.min_weight ?? errors.max_weight}</p>
                    )}
                    <p className="text-muted-foreground text-xs">
                        Same number in all three for a fixed-point item (mandatory sections usually are). A wider min–max band leaves the HOD room to
                        set the exact weight when assigning it to a card.
                    </p>
                    <div className="flex flex-wrap items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.requires_quantity} onCheckedChange={(v) => setData('requires_quantity', v === true)} />
                            Requires a quantity
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.evidence_required} onCheckedChange={(v) => setData('evidence_required', v === true)} />
                            Evidence required
                        </label>
                    </div>
                    {data.requires_quantity && (
                        <div>
                            <Label className="text-xs">Quantity unit</Label>
                            <Input
                                value={data.quantity_unit}
                                onChange={(e) => setData('quantity_unit', e.target.value)}
                                placeholder="e.g. articles, links"
                            />
                        </div>
                    )}
                    <div>
                        <Label className="text-xs">Evidence type (optional)</Label>
                        <Input
                            value={data.evidence_type}
                            onChange={(e) => setData('evidence_type', e.target.value)}
                            placeholder="e.g. url, screenshot, report"
                        />
                    </div>
                    <div>
                        <Label className="text-xs">Completion criteria — shown to the employee as the definition of done</Label>
                        <textarea
                            className="border-input min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.completion_criteria}
                            onChange={(e) => setData('completion_criteria', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label className="text-xs">Internal notes (optional, not shown to the employee)</Label>
                        <textarea
                            className="border-input min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {method === 'post' ? 'Add template' : 'Save changes'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
    const reactivate = (t: Template) =>
        router.patch(`/seo-board/templates/${t.id}`, { ...templateToFormValues(t), is_active: true }, { preserveScroll: true });
    const daily = templates.filter((t) => t.card_type === 'daily');
    const weekly = templates.filter((t) => t.card_type === 'weekly');

    const renderGroup = (label: string, cardType: string, items: Template[]) => (
        <Card className="p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{label}</h2>
                <TemplateFormDialog
                    title={`Add a ${label.toLowerCase()} task`}
                    initial={blankTemplate(cardType, 'production')}
                    url="/seo-board/templates"
                    method="post"
                    trigger={
                        <Button size="sm" variant="outline">
                            Add template
                        </Button>
                    }
                />
            </div>
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
                            <TemplateFormDialog
                                title={`Edit "${t.name}"`}
                                initial={templateToFormValues(t)}
                                url={`/seo-board/templates/${t.id}`}
                                method="patch"
                                trigger={
                                    <Button size="sm" variant="outline">
                                        Edit
                                    </Button>
                                }
                            />
                            {t.is_active ? (
                                <Button size="sm" variant="outline" onClick={() => deactivate(t.id)}>
                                    Deactivate
                                </Button>
                            ) : (
                                <Button size="sm" variant="outline" onClick={() => reactivate(t)}>
                                    Reactivate
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
                {items.length === 0 && <p className="text-muted-foreground text-sm">No templates yet.</p>}
            </div>
        </Card>
    );

    return (
        <div className="flex flex-col gap-4">
            <p className="text-muted-foreground text-sm">
                Mandatory sections are seeded onto every card automatically; editing one changes what future cards get, not cards already created.
                Production-work items are a weight band — the HOD picks the exact number when assigning one to a day's or week's card.
            </p>
            {renderGroup('Daily card', 'daily', daily)}
            {renderGroup('Weekly card', 'weekly', weekly)}
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

    const tourSteps: TourStep[] = [
        ...(can.templates && can.notifications
            ? [
                  {
                      target: 'settings-tabs',
                      onShow: () => setTab(can.templates ? 'templates' : 'notifications'),
                      title: 'Two things live here',
                      text: 'The task library your HOD assigns from, and who gets the automatic nightly close-of-day report.',
                  } satisfies TourStep,
              ]
            : []),
        ...(can.templates
            ? [
                  {
                      target: 'settings-templates',
                      onShow: () => setTab('templates'),
                      title: 'The task library',
                      text: "Mandatory items are fixed and can't be reweighted here. Production items have a flexible weight range — your HOD picks the exact weight when assigning one to a day.",
                  } satisfies TourStep,
              ]
            : []),
        ...(can.notifications
            ? [
                  {
                      target: 'settings-notifications',
                      onShow: () => setTab('notifications'),
                      title: 'Where the nightly report goes',
                      text: 'The HOD is included automatically. Add anyone else — like the CEO or a deputy — who should also get the close-of-day email.',
                  } satisfies TourStep,
              ]
            : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SEO Board Settings" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">SEO Board Settings — {department.name}</h1>
                    <SeoBoardTour tourKey="settings" steps={tourSteps} />
                </div>

                {can.templates && can.notifications && (
                    <div className="flex gap-1 border-b" data-tour="settings-tabs">
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

                {tab === 'templates' && can.templates && (
                    <div data-tour="settings-templates">
                        <TemplatesTab templates={templates} />
                    </div>
                )}
                {tab === 'notifications' && can.notifications && (
                    <div data-tour="settings-notifications">
                        <NotificationsTab department={department} hod={hod} recipients={recipients} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
