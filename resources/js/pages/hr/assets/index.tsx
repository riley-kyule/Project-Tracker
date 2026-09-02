import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

type Category = { id: number; name: string; is_active: boolean };

type AssetRow = {
    id: number;
    asset_tag: string;
    name: string;
    category: { id: number; name: string } | null;
    serial_number: string | null;
    status: string;
    condition: string;
    custodian: { id: number; name: string } | null;
};

type PageProps = {
    assets: AssetRow[];
    categories: Category[];
    canManage: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assets', href: '/hr/assets' }];

const NONE = 'none';
const STATUSES = ['in_stock', 'assigned', 'in_repair', 'retired', 'lost'];
const CONDITIONS = ['new', 'good', 'fair', 'poor'];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    in_stock: 'secondary',
    assigned: 'default',
    in_repair: 'outline',
    retired: 'outline',
    lost: 'destructive',
};

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function CreateAssetDialog({ categories }: { categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        asset_tag: '',
        name: '',
        asset_category_id: NONE,
        serial_number: '',
        manufacturer: '',
        model: '',
        purchase_date: '',
        purchase_cost: '',
        supplier: '',
        warranty_expiry: '',
        status: 'in_stock',
        condition: 'good',
        location: '',
        description: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            asset_category_id: form.asset_category_id === NONE ? null : Number(form.asset_category_id),
            purchase_cost: form.purchase_cost === '' ? null : Number(form.purchase_cost),
            purchase_date: form.purchase_date || null,
            warranty_expiry: form.warranty_expiry || null,
        }));
        post('/hr/assets', {
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
                    <Plus className="mr-1 h-4 w-4" /> New asset
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>New asset</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="asset_tag">Asset tag</Label>
                        <Input id="asset_tag" value={data.asset_tag} onChange={(e) => setData('asset_tag', e.target.value)} />
                        <InputError message={errors.asset_tag} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="asset_category_id">Category</Label>
                        <Select value={data.asset_category_id} onValueChange={(v) => setData('asset_category_id', v)}>
                            <SelectTrigger id="asset_category_id">
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
                    <div className="grid gap-1.5">
                        <Label htmlFor="serial_number">Serial number</Label>
                        <Input id="serial_number" value={data.serial_number} onChange={(e) => setData('serial_number', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="manufacturer">Manufacturer</Label>
                        <Input id="manufacturer" value={data.manufacturer} onChange={(e) => setData('manufacturer', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="model">Model</Label>
                        <Input id="model" value={data.model} onChange={(e) => setData('model', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="purchase_date">Purchase date</Label>
                        <Input id="purchase_date" type="date" value={data.purchase_date} onChange={(e) => setData('purchase_date', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="purchase_cost">Purchase cost (KES)</Label>
                        <Input
                            id="purchase_cost"
                            type="number"
                            step="0.01"
                            value={data.purchase_cost}
                            onChange={(e) => setData('purchase_cost', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="supplier">Supplier</Label>
                        <Input id="supplier" value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="warranty_expiry">Warranty expiry</Label>
                        <Input
                            id="warranty_expiry"
                            type="date"
                            value={data.warranty_expiry}
                            onChange={(e) => setData('warranty_expiry', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="status">Status</Label>
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger id="status">
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
                        <Label htmlFor="condition">Condition</Label>
                        <Select value={data.condition} onValueChange={(v) => setData('condition', v)}>
                            <SelectTrigger id="condition">
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
                        <Label htmlFor="location">Location</Label>
                        <Input id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                    </div>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Create
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CategoryDialog({ categories }: { categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', is_active: true });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hr/asset-categories', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Categories</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Asset categories</DialogTitle>
                </DialogHeader>
                <ul className="mb-3 grid gap-1 text-sm">
                    {categories.map((c) => (
                        <li key={c.id} className="rounded border px-3 py-1.5">
                            {c.name}
                        </li>
                    ))}
                </ul>
                <form onSubmit={submit} className="flex items-end gap-2">
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="cat-name">New category</Label>
                        <Input id="cat-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Add
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AssetsIndex({ assets, categories, canManage }: PageProps) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return assets.filter((a) => {
            if (statusFilter !== 'all' && a.status !== statusFilter) return false;
            if (!q) return true;
            return (
                a.name.toLowerCase().includes(q) ||
                a.asset_tag.toLowerCase().includes(q) ||
                (a.serial_number ?? '').toLowerCase().includes(q) ||
                (a.custodian?.name ?? '').toLowerCase().includes(q)
            );
        });
    }, [assets, search, statusFilter]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Assets" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Assets</h1>
                        <p className="text-muted-foreground text-sm">{filtered.length} assets</p>
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            <CategoryDialog categories={categories} />
                            <CreateAssetDialog categories={categories} />
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search tag, name, serial, custodian…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-xs"
                    />
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUSES.map((s) => (
                                <SelectItem key={s} value={s}>
                                    {label(s)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Tag</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Category</th>
                                <th className="px-3 py-2 font-medium">Serial</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Custodian</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((a) => (
                                <tr key={a.id} className="hover:bg-muted/30 border-t">
                                    <td className="text-muted-foreground px-3 py-2">{a.asset_tag}</td>
                                    <td className="px-3 py-2">
                                        <Link href={`/hr/assets/${a.id}`} className="text-primary font-medium hover:underline">
                                            {a.name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{a.category?.name ?? '—'}</td>
                                    <td className="text-muted-foreground px-3 py-2">{a.serial_number ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={STATUS_VARIANT[a.status] ?? 'outline'}>{label(a.status)}</Badge>
                                    </td>
                                    <td className="px-3 py-2">{a.custodian?.name ?? '—'}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                        No assets match.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
