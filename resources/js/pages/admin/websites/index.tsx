import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

type Option = { id: number; name: string };

type WebsiteRow = {
    id: number;
    name: string;
    domain: string | null;
    status: string;
    country: Option | null;
    responsible_department: Option | null;
    responsible_user: Option | null;
    ga4_property_id: string | null;
    gsc_property: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Websites', href: '/admin/websites' }];

const NONE = 'none';

function WebsiteDialog({
    website,
    countries,
    departments,
    users,
    trigger,
}: {
    website?: WebsiteRow;
    countries: Option[];
    departments: Option[];
    users: Option[];
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, transform } = useForm({
        name: website?.name ?? '',
        domain: website?.domain ?? '',
        country_id: website?.country?.id.toString() ?? NONE,
        status: website?.status ?? 'active',
        responsible_department_id: website?.responsible_department?.id.toString() ?? NONE,
        responsible_user_id: website?.responsible_user?.id.toString() ?? NONE,
        ga4_property_id: website?.ga4_property_id ?? '',
        gsc_property: website?.gsc_property ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            country_id: form.country_id === NONE ? null : Number(form.country_id),
            responsible_department_id: form.responsible_department_id === NONE ? null : Number(form.responsible_department_id),
            responsible_user_id: form.responsible_user_id === NONE ? null : Number(form.responsible_user_id),
        }));
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (website) {
            patch(`/admin/websites/${website.id}`, options);
        } else {
            post('/admin/websites', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{website ? `Edit ${website.name}` : 'New website'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="site-name">Name</Label>
                        <Input id="site-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="site-domain">Domain</Label>
                        <Input id="site-domain" value={data.domain} onChange={(e) => setData('domain', e.target.value)} />
                        <InputError message={errors.domain} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Country</Label>
                        <Select value={data.country_id} onValueChange={(value) => setData('country_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No country" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No country</SelectItem>
                                {countries.map((country) => (
                                    <SelectItem key={country.id} value={country.id.toString()}>
                                        {country.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Responsible department</Label>
                        <Select value={data.responsible_department_id} onValueChange={(value) => setData('responsible_department_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>None</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Responsible person</Label>
                        <Select value={data.responsible_user_id} onValueChange={(value) => setData('responsible_user_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>None</SelectItem>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={user.id.toString()}>
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor="ga4">GA4 property</Label>
                            <Input id="ga4" value={data.ga4_property_id} onChange={(e) => setData('ga4_property_id', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="gsc">GSC property</Label>
                            <Input id="gsc" value={data.gsc_property} onChange={(e) => setData('gsc_property', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Status</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="archived">Archived</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {website ? 'Save changes' : 'Add website'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function WebsitesIndex({
    websites,
    countries,
    departments,
    users,
    canManage,
}: {
    websites: WebsiteRow[];
    countries: Option[];
    departments: Option[];
    users: Option[];
    canManage: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Websites" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Website registry</h1>
                    {canManage && (
                        <WebsiteDialog
                            countries={countries}
                            departments={departments}
                            users={users}
                            trigger={
                                <Button size="sm">
                                    <Plus className="mr-1 size-4" /> New website
                                </Button>
                            }
                        />
                    )}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Country</th>
                                <th className="p-3 font-medium">Responsible</th>
                                <th className="p-3 font-medium">GA4 / GSC</th>
                                <th className="p-3 font-medium">Status</th>
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {websites.map((website) => (
                                <tr key={website.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-medium">{website.name}</td>
                                    <td className="p-3">{website.country?.name ?? '—'}</td>
                                    <td className="p-3">{website.responsible_user?.name ?? website.responsible_department?.name ?? '—'}</td>
                                    <td className="p-3 text-xs">
                                        {website.ga4_property_id ? 'GA4 ✓' : 'GA4 —'} / {website.gsc_property ? 'GSC ✓' : 'GSC —'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={website.status === 'active' ? 'default' : 'secondary'}>{website.status}</Badge>
                                    </td>
                                    {canManage && (
                                        <td className="p-3 text-right">
                                            <WebsiteDialog
                                                website={website}
                                                countries={countries}
                                                departments={departments}
                                                users={users}
                                                trigger={
                                                    <Button variant="ghost" size="sm" aria-label={`Edit ${website.name}`}>
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                }
                                            />
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {websites.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                        No websites registered yet.
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
