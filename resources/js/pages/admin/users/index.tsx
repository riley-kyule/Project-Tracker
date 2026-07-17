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

type UserRow = {
    id: number;
    name: string;
    email: string;
    department: { id: number; name: string } | null;
    manager_id: number | null;
    job_title: string | null;
    status: 'active' | 'inactive' | 'suspended';
    role: string | null;
    last_login_at: string | null;
};

type DepartmentOption = { id: number; name: string };

type UserForm = {
    department_id: string;
    manager_id: string;
    job_title: string;
    status: string;
    role: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/admin/users' }];

const NONE = 'none';

const statusVariant: Record<UserRow['status'], 'default' | 'secondary' | 'destructive'> = {
    active: 'default',
    inactive: 'secondary',
    suspended: 'destructive',
};

type NewUserForm = {
    name: string;
    email: string;
    department_id: string;
    job_title: string;
    status: string;
    role: string;
};

function NewUserDialog({ departments, roles }: { departments: DepartmentOption[]; roles: string[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm<NewUserForm>({
        name: '',
        email: '',
        department_id: NONE,
        job_title: '',
        status: 'active',
        role: 'Employee',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, department_id: form.department_id === NONE ? null : Number(form.department_id) }));
        post('/admin/users', {
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
                <Button size="sm">
                    <Plus className="mr-1 size-4" /> New user
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New user</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        <p className="text-muted-foreground text-xs">Must match their company Google account — that's how they'll sign in.</p>
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {role}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Department</Label>
                        <Select value={data.department_id} onValueChange={(value) => setData('department_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No department</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.department_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="job_title">Job title</Label>
                        <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                        <InputError message={errors.job_title} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create user
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({ user, users, departments, roles }: { user: UserRow; users: UserRow[]; departments: DepartmentOption[]; roles: string[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, transform } = useForm<UserForm>({
        department_id: user.department?.id.toString() ?? NONE,
        manager_id: user.manager_id?.toString() ?? NONE,
        job_title: user.job_title ?? '',
        status: user.status,
        role: user.role ?? 'Employee',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === NONE ? null : Number(form.department_id),
            manager_id: form.manager_id === NONE ? null : Number(form.manager_id),
        }));
        patch(`/admin/users/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" aria-label={`Edit ${user.name}`}>
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {user.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {role}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Department</Label>
                        <Select value={data.department_id} onValueChange={(value) => setData('department_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No department</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.department_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Manager</Label>
                        <Select value={data.manager_id} onValueChange={(value) => setData('manager_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="No manager" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No manager</SelectItem>
                                {users
                                    .filter((candidate) => candidate.id !== user.id)
                                    .map((candidate) => (
                                        <SelectItem key={candidate.id} value={candidate.id.toString()}>
                                            {candidate.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.manager_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="job_title">Job title</Label>
                        <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                        <InputError message={errors.job_title} />
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
                                <SelectItem value="suspended">Suspended</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save changes
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function UsersIndex({
    users,
    departments,
    roles,
    canManage,
}: {
    users: UserRow[];
    departments: DepartmentOption[];
    roles: string[];
    canManage: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Users</h1>
                    {canManage && <NewUserDialog departments={departments} roles={roles} />}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Role</th>
                                <th className="p-3 font-medium">Department</th>
                                <th className="p-3 font-medium">Job title</th>
                                <th className="p-3 font-medium">Status</th>
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3">
                                        <div className="font-medium">{user.name}</div>
                                        <div className="text-muted-foreground">{user.email}</div>
                                    </td>
                                    <td className="p-3">{user.role ?? '—'}</td>
                                    <td className="p-3">{user.department?.name ?? '—'}</td>
                                    <td className="p-3">{user.job_title ?? '—'}</td>
                                    <td className="p-3">
                                        <Badge variant={statusVariant[user.status]}>{user.status}</Badge>
                                    </td>
                                    {canManage && (
                                        <td className="p-3 text-right">
                                            <EditUserDialog user={user} users={users} departments={departments} roles={roles} />
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
