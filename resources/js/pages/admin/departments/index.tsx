import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

type Department = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    manager_id: number | null;
    manager: { id: number; name: string } | null;
    is_active: boolean;
    users_count: number;
};

type Manager = { id: number; name: string };

type DepartmentForm = {
    name: string;
    description: string;
    manager_id: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Departments', href: '/admin/departments' }];

const NONE = 'none';

function DepartmentDialog({ department, managers, trigger }: { department?: Department; managers: Manager[]; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset, transform } = useForm<DepartmentForm>({
        name: department?.name ?? '',
        description: department?.description ?? '',
        manager_id: department?.manager_id?.toString() ?? NONE,
        is_active: department?.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            manager_id: form.manager_id === NONE ? null : Number(form.manager_id),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        };

        if (department) {
            patch(`/admin/departments/${department.id}`, options);
        } else {
            post('/admin/departments', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{department ? `Edit ${department.name}` : 'New department'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Manager</Label>
                        <Select value={data.manager_id} onValueChange={(value) => setData('manager_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No manager" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No manager</SelectItem>
                                {managers.map((manager) => (
                                    <SelectItem key={manager.id} value={manager.id.toString()}>
                                        {manager.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.manager_id} />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                        <Label htmlFor="is_active">Active</Label>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {department ? 'Save changes' : 'Create department'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function DepartmentsIndex({
    departments,
    managers,
    canManage,
}: {
    departments: Department[];
    managers: Manager[];
    canManage: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Departments</h1>
                    {canManage && (
                        <DepartmentDialog
                            managers={managers}
                            trigger={
                                <Button size="sm">
                                    <Plus className="mr-1 size-4" /> New department
                                </Button>
                            }
                        />
                    )}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Manager</th>
                                <th className="p-3 font-medium">Members</th>
                                <th className="p-3 font-medium">Status</th>
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {departments.map((department) => (
                                <tr key={department.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-medium">{department.name}</td>
                                    <td className="p-3">{department.manager?.name ?? '—'}</td>
                                    <td className="p-3">{department.users_count}</td>
                                    <td className="p-3">
                                        <Badge variant={department.is_active ? 'default' : 'secondary'}>
                                            {department.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </td>
                                    {canManage && (
                                        <td className="p-3 text-right">
                                            <DepartmentDialog
                                                department={department}
                                                managers={managers}
                                                trigger={
                                                    <Button variant="ghost" size="sm" aria-label={`Edit ${department.name}`}>
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                }
                                            />
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
