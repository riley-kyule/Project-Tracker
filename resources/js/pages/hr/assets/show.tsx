import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

type Ref = { id: number; name: string };

type Assignment = {
    id: number;
    employee: Ref | null;
    assigned_by: string | null;
    assigned_at: string;
    expected_return_at: string | null;
    returned_at: string | null;
    condition_out: string | null;
    condition_in: string | null;
    notes: string | null;
};

type Asset = {
    id: number;
    asset_tag: string;
    asset_category_id: number | null;
    name: string;
    description: string | null;
    serial_number: string | null;
    manufacturer: string | null;
    model: string | null;
    purchase_date: string | null;
    purchase_cost: string | null;
    supplier: string | null;
    warranty_expiry: string | null;
    status: string;
    condition: string;
    location: string | null;
    notes: string | null;
    category: Ref | null;
    current_assignment_id: number | null;
    assignments: Assignment[];
};

type PageProps = {
    asset: Asset;
    categories: Ref[];
    employees: Ref[];
    canManage: boolean;
};

const CONDITIONS = ['new', 'good', 'fair', 'poor'];

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function Field({ label: l, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-muted-foreground text-xs">{l}</span>
            <span className="text-sm">{value ?? '—'}</span>
        </div>
    );
}

const NONE = 'none';
const STATUSES = ['in_stock', 'assigned', 'in_repair', 'retired', 'lost'];

function EditAssetDialog({ asset, categories }: { asset: Asset; categories: Ref[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, transform } = useForm({
        asset_tag: asset.asset_tag,
        name: asset.name,
        asset_category_id: asset.asset_category_id ? String(asset.asset_category_id) : NONE,
        serial_number: asset.serial_number ?? '',
        manufacturer: asset.manufacturer ?? '',
        model: asset.model ?? '',
        purchase_date: (asset.purchase_date ?? '').slice(0, 10),
        purchase_cost: asset.purchase_cost ?? '',
        supplier: asset.supplier ?? '',
        warranty_expiry: (asset.warranty_expiry ?? '').slice(0, 10),
        status: asset.status,
        condition: asset.condition,
        location: asset.location ?? '',
        description: asset.description ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({
            ...f,
            asset_category_id: f.asset_category_id === NONE ? null : Number(f.asset_category_id),
            purchase_cost: f.purchase_cost === '' ? null : Number(f.purchase_cost),
            purchase_date: f.purchase_date || null,
            warranty_expiry: f.warranty_expiry || null,
        }));
        patch(`/hr/assets/${asset.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    const text = (name: keyof typeof data, l: string, type = 'text') => (
        <div className="grid gap-1.5">
            <Label htmlFor={`e-${name}`}>{l}</Label>
            {type === 'date' ? (
                <DateField id={`e-${name}`} value={data[name] as string} onChange={(v) => setData(name, v)} />
            ) : (
                <Input id={`e-${name}`} type={type} value={data[name] as string} onChange={(ev) => setData(name, ev.target.value)} />
            )}
            <InputError message={errors[name as keyof typeof errors]} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="mr-1 h-4 w-4" /> Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit {asset.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    {text('asset_tag', 'Asset tag')}
                    {text('name', 'Name')}
                    <div className="grid gap-1.5">
                        <Label htmlFor="e-cat">Category</Label>
                        <Select value={data.asset_category_id} onValueChange={(v) => setData('asset_category_id', v)}>
                            <SelectTrigger id="e-cat">
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>None</SelectItem>
                                {categories.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {text('serial_number', 'Serial number')}
                    {text('manufacturer', 'Manufacturer')}
                    {text('model', 'Model')}
                    {text('purchase_date', 'Purchase date', 'date')}
                    {text('purchase_cost', 'Purchase cost (KES)', 'number')}
                    {text('supplier', 'Supplier')}
                    {text('warranty_expiry', 'Warranty expiry', 'date')}
                    <div className="grid gap-1.5">
                        <Label htmlFor="e-status">Status</Label>
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger id="e-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {label(s)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="e-condition">Condition</Label>
                        <Select value={data.condition} onValueChange={(v) => setData('condition', v)}>
                            <SelectTrigger id="e-condition">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CONDITIONS.map((c) => (
                                    <SelectItem key={c} value={c}>
                                        {label(c)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {text('location', 'Location')}
                    {text('description', 'Description')}
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AssignDialog({ asset, employees }: { asset: Asset; employees: Ref[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        employee_id: '',
        expected_return_at: '',
        condition_out: asset.condition,
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, employee_id: Number(form.employee_id), expected_return_at: form.expected_return_at || null }));
        post(`/hr/assets/${asset.id}/assignments`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Assign</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assign {asset.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="employee_id">Custodian</Label>
                        <Combobox
                            id="employee_id"
                            value={data.employee_id}
                            onChange={(v) => setData('employee_id', v)}
                            placeholder="Select employee"
                            options={employees.map((emp) => ({ value: String(emp.id), label: emp.name }))}
                        />
                        <InputError message={errors.employee_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="expected_return_at">Expected return</Label>
                        <DateField id="expected_return_at" value={data.expected_return_at} onChange={(v) => setData('expected_return_at', v)} />
                        <InputError message={errors.expected_return_at} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="condition_out">Condition out</Label>
                        <Select value={data.condition_out} onValueChange={(v) => setData('condition_out', v)}>
                            <SelectTrigger id="condition_out">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CONDITIONS.map((c) => (
                                    <SelectItem key={c} value={c}>
                                        {label(c)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="assign-notes">Notes</Label>
                        <Input id="assign-notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing || !data.employee_id}>
                        Assign
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReturnDialog({ asset }: { asset: Asset }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, transform } = useForm({
        condition_in: 'good',
        new_status: 'in_stock',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, notes: form.notes || null }));
        patch(`/hr/assets/${asset.id}/assignments/${asset.current_assignment_id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Record return
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record return</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="condition_in">Condition in</Label>
                        <Select value={data.condition_in} onValueChange={(v) => setData('condition_in', v)}>
                            <SelectTrigger id="condition_in">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CONDITIONS.map((c) => (
                                    <SelectItem key={c} value={c}>
                                        {label(c)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="new_status">New status</Label>
                        <Select value={data.new_status} onValueChange={(v) => setData('new_status', v)}>
                            <SelectTrigger id="new_status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['in_stock', 'in_repair', 'retired', 'lost'].map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {label(s)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="return-notes">Notes</Label>
                        <Input id="return-notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AssetShow({ asset, categories, employees, canManage }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Assets', href: '/hr/assets' },
        { title: asset.name, href: `/hr/assets/${asset.id}` },
    ];

    const open = asset.assignments.find((a) => a.returned_at === null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={asset.name} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{asset.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {asset.asset_tag} · {asset.category?.name ?? 'Uncategorised'}
                        </p>
                        <div className="mt-1 flex items-center gap-2">
                            <Badge>{label(asset.status)}</Badge>
                            <span className="text-muted-foreground text-xs">Condition: {label(asset.condition)}</span>
                            {open?.employee && (
                                <span className="text-muted-foreground text-xs">
                                    · Held by{' '}
                                    <Link href={`/hr/employees/${open.employee.id}`} className="text-primary hover:underline">
                                        {open.employee.name}
                                    </Link>
                                </span>
                            )}
                        </div>
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            {open ? <ReturnDialog asset={asset} /> : <AssignDialog asset={asset} employees={employees} />}
                            <EditAssetDialog asset={asset} categories={categories} />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (confirm('Remove this asset?')) router.delete(`/hr/assets/${asset.id}`);
                                }}
                            >
                                Delete
                            </Button>
                        </div>
                    )}
                </div>

                <Card className="p-4">
                    <h2 className="mb-3 text-sm font-semibold">Details</h2>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Field label="Serial number" value={asset.serial_number} />
                        <Field label="Manufacturer" value={asset.manufacturer} />
                        <Field label="Model" value={asset.model} />
                        <Field label="Location" value={asset.location} />
                        <Field label="Purchase date" value={fmtDate(asset.purchase_date)} />
                        <Field label="Purchase cost" value={asset.purchase_cost ? `KES ${asset.purchase_cost}` : null} />
                        <Field label="Supplier" value={asset.supplier} />
                        <Field label="Warranty expiry" value={fmtDate(asset.warranty_expiry)} />
                    </div>
                    {asset.description && <p className="text-muted-foreground mt-3 text-sm">{asset.description}</p>}
                </Card>

                <Card className="p-4">
                    <h2 className="mb-3 text-sm font-semibold">Assignment history</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground text-left">
                                <tr>
                                    <th className="py-1 pr-3">Custodian</th>
                                    <th className="py-1 pr-3">Assigned</th>
                                    <th className="py-1 pr-3">Expected return</th>
                                    <th className="py-1 pr-3">Returned</th>
                                    <th className="py-1 pr-3">Condition out/in</th>
                                    <th className="py-1 pr-3">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {asset.assignments.map((a) => (
                                    <tr key={a.id} className="border-t">
                                        <td className="py-1.5 pr-3">{a.employee?.name ?? '—'}</td>
                                        <td className="py-1.5 pr-3">{fmtDate(a.assigned_at)}</td>
                                        <td className="py-1.5 pr-3">{a.expected_return_at ? fmtDate(a.expected_return_at) : '—'}</td>
                                        <td className="py-1.5 pr-3">{a.returned_at ? fmtDate(a.returned_at) : <Badge>Open</Badge>}</td>
                                        <td className="py-1.5 pr-3">
                                            {(a.condition_out ? label(a.condition_out) : '—') +
                                                ' / ' +
                                                (a.condition_in ? label(a.condition_in) : '—')}
                                        </td>
                                        <td className="text-muted-foreground py-1.5 pr-3">{a.assigned_by ?? '—'}</td>
                                    </tr>
                                ))}
                                {asset.assignments.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground py-4 text-center">
                                            Never assigned.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
