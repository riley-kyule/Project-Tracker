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
    parent_department_id: number | null;
    parent: { id: number; name: string } | null;
    manager_id: number | null;
    manager: { id: number; name: string } | null;
    assistant_manager_id: number | null;
    assistant_manager: { id: number; name: string } | null;
    is_active: boolean;
    daily_summary_time: string | null;
    users_count: number;
};

type Manager = { id: number; name: string };
type ParentOption = { id: number; name: string };

type DepartmentForm = {
    name: string;
    description: string;
    parent_department_id: string;
    manager_id: string;
    assistant_manager_id: string;
    is_active: boolean;
    daily_summary_time: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Departments', href: '/admin/departments' }];

const NONE = 'none';

function DepartmentDialog({
    department,
    managers,
    parentOptions,
    trigger,
}: {
    department?: Department;
    managers: Manager[];
    parentOptions: ParentOption[];
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset, transform } = useForm<DepartmentForm>({
        name: department?.name ?? '',
        description: department?.description ?? '',
        parent_department_id: department?.parent_department_id?.toString() ?? NONE,
        manager_id: department?.manager_id?.toString() ?? NONE,
        assistant_manager_id: department?.assistant_manager_id?.toString() ?? NONE,
        is_active: department?.is_active ?? true,
        daily_summary_time: department?.daily_summary_time?.slice(0, 5) ?? '',
    });

    // A department that already has sub-departments can't become one itself.
    const availableParents = parentOptions.filter((option) => option.id !== department?.id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            parent_department_id: form.parent_department_id === NONE ? null : Number(form.parent_department_id),
            manager_id: form.manager_id === NONE ? null : Number(form.manager_id),
            assistant_manager_id: form.assistant_manager_id === NONE ? null : Number(form.assistant_manager_id),
            daily_summary_time: form.daily_summary_time === '' ? null : form.daily_summary_time,
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
                        <Label>Parent department</Label>
                        <Select value={data.parent_department_id} onValueChange={(value) => setData('parent_department_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Top-level department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Top-level department</SelectItem>
                                {availableParents.map((option) => (
                                    <SelectItem key={option.id} value={option.id.toString()}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.parent_department_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Head of department</Label>
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
                    <div className="grid gap-2">
                        <Label>Assistant manager</Label>
                        <Select value={data.assistant_manager_id} onValueChange={(value) => setData('assistant_manager_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No assistant" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No assistant</SelectItem>
                                {managers.map((manager) => (
                                    <SelectItem key={manager.id} value={manager.id.toString()}>
                                        {manager.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.assistant_manager_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="daily_summary_time">Daily summary email time</Label>
                        <Input
                            id="daily_summary_time"
                            type="time"
                            value={data.daily_summary_time}
                            onChange={(e) => setData('daily_summary_time', e.target.value)}
                            className="w-40"
                        />
                        <p className="text-muted-foreground text-xs">
                            Sent to the head of department and assistant manager. Leave blank to disable.
                        </p>
                        <InputError message={errors.daily_summary_time} />
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

function CompanySummarySettings({ ceoSummaryTime }: { ceoSummaryTime: string | null }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        ceo_summary_time: ceoSummaryTime?.slice(0, 5) ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/admin/company-settings', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="border-sidebar-border/70 dark:border-sidebar-border flex items-end gap-3 rounded-xl border p-4">
            <div className="grid gap-2">
                <Label htmlFor="ceo_summary_time">CEO daily summary email time</Label>
                <Input
                    id="ceo_summary_time"
                    type="time"
                    value={data.ceo_summary_time}
                    onChange={(e) => setData('ceo_summary_time', e.target.value)}
                    className="w-40"
                />
                <InputError message={errors.ceo_summary_time} />
            </div>
            <Button type="submit" size="sm" disabled={processing}>
                Save
            </Button>
            {recentlySuccessful && <span className="text-muted-foreground text-xs">Saved.</span>}
        </form>
    );
}

export default function DepartmentsIndex({
    departments,
    managers,
    parentOptions,
    canManage,
    companySettings,
}: {
    departments: Department[];
    managers: Manager[];
    parentOptions: ParentOption[];
    canManage: boolean;
    companySettings: { ceo_summary_time: string | null };
}) {
    // Group children directly beneath their parent so a division and its teams read as one unit.
    const topLevel = departments.filter((department) => !department.parent_department_id);
    const orderedDepartments = topLevel.flatMap((parent) => [
        parent,
        ...departments.filter((department) => department.parent_department_id === parent.id),
    ]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Departments</h1>
                    {canManage && (
                        <DepartmentDialog
                            managers={managers}
                            parentOptions={parentOptions}
                            trigger={
                                <Button size="sm">
                                    <Plus className="mr-1 size-4" /> New department
                                </Button>
                            }
                        />
                    )}
                </div>
                {canManage && <CompanySummarySettings ceoSummaryTime={companySettings.ceo_summary_time} />}
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Head</th>
                                <th className="p-3 font-medium">Assistant</th>
                                <th className="p-3 font-medium">Members</th>
                                <th className="p-3 font-medium">Status</th>
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {orderedDepartments.map((department) => (
                                <tr key={department.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className={`p-3 font-medium ${department.parent_department_id ? 'pl-8 font-normal' : ''}`}>
                                        {department.parent_department_id && <span className="text-muted-foreground mr-1">↳</span>}
                                        {department.name}
                                    </td>
                                    <td className="p-3">{department.manager?.name ?? '—'}</td>
                                    <td className="p-3">{department.assistant_manager?.name ?? '—'}</td>
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
                                                parentOptions={parentOptions}
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
