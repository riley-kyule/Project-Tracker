import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type RequestPayload } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';

type Website = { id: number; name: string; domain: string | null };

type WordPressUserRow = {
    id: number;
    website: Website;
    username: string;
    email: string | null;
    display_name: string | null;
    roles: string[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'WordPress Users', href: '/admin/wordpress-users' }];

const ALL = 'all';

function RoleCheckboxes({ roles, selected, onChange }: { roles: string[]; selected: string[]; onChange: (roles: string[]) => void }) {
    const toggle = (role: string) => {
        onChange(selected.includes(role) ? selected.filter((r) => r !== role) : [...selected, role]);
    };

    return (
        <div className="flex flex-wrap gap-3">
            {roles.map((role) => (
                <label key={role} className="flex items-center gap-1.5 text-sm capitalize">
                    <Checkbox checked={selected.includes(role)} onCheckedChange={() => toggle(role)} /> {role}
                </label>
            ))}
        </div>
    );
}

function AddUserDialog({ websites, roles }: { websites: Website[]; roles: string[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        website_ids: [] as number[],
        username: '',
        email: '',
        password: '',
        roles: [] as string[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/wordpress-users/bulk-add', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    const toggleWebsite = (id: number) => {
        setData('website_ids', data.website_ids.includes(id) ? data.website_ids.filter((w) => w !== id) : [...data.website_ids, id]);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="mr-1 size-4" /> Add user
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Add a WordPress user</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Websites</Label>
                        <div className="max-h-40 space-y-1.5 overflow-y-auto rounded-md border p-2">
                            {websites.map((website) => (
                                <label key={website.id} className="flex items-center gap-1.5 text-sm">
                                    <Checkbox checked={data.website_ids.includes(website.id)} onCheckedChange={() => toggleWebsite(website.id)} />
                                    {website.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.website_ids} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-username">Username</Label>
                        <Input id="new-username" value={data.username} onChange={(e) => setData('username', e.target.value)} required />
                        <InputError message={errors.username} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-email">Email</Label>
                        <Input id="new-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-password">Password</Label>
                        <Input
                            id="new-password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            minLength={12}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Roles</Label>
                        <RoleCheckboxes roles={roles} selected={data.roles} onChange={(value) => setData('roles', value)} />
                        <InputError message={errors.roles} />
                    </div>
                    <Button type="submit" disabled={processing || data.website_ids.length === 0}>
                        Add to {data.website_ids.length || 0} site(s)
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ChangeRoleDialog({ roles, onApply }: { roles: string[]; onApply: (roles: string[]) => void }) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);

    const apply = () => {
        setProcessing(true);
        onApply(selected);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) setProcessing(false);
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Change role
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change role for selected users</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <RoleCheckboxes roles={roles} selected={selected} onChange={setSelected} />
                    <Button onClick={apply} disabled={processing || selected.length === 0}>
                        Apply
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function UpdateEmailDialog({
    selectedUsers,
    onApply,
}: {
    selectedUsers: WordPressUserRow[];
    onApply: (updates: { id: number; email: string }[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [emails, setEmails] = useState<Record<number, string>>({});
    const [processing, setProcessing] = useState(false);

    const openDialog = (next: boolean) => {
        setOpen(next);
        if (next) {
            setEmails(Object.fromEntries(selectedUsers.map((u) => [u.id, u.email ?? ''])));
        } else {
            setProcessing(false);
        }
    };

    const apply = () => {
        setProcessing(true);
        onApply(selectedUsers.map((u) => ({ id: u.id, email: emails[u.id] ?? '' })));
    };

    return (
        <Dialog open={open} onOpenChange={openDialog}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Update emails
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Update emails</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    {selectedUsers.map((user) => (
                        <div key={user.id} className="grid gap-1">
                            <Label className="text-xs">
                                {user.username} · {user.website.name}
                            </Label>
                            <Input
                                type="email"
                                value={emails[user.id] ?? ''}
                                onChange={(e) => setEmails((current) => ({ ...current, [user.id]: e.target.value }))}
                            />
                        </div>
                    ))}
                    <Button onClick={apply} disabled={processing}>
                        Save {selectedUsers.length} email(s)
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function BulkActionBar({
    selectedIds,
    selectedUsers,
    roles,
    onDone,
}: {
    selectedIds: number[];
    selectedUsers: WordPressUserRow[];
    roles: string[];
    onDone: () => void;
}) {
    const post = (url: string, data: RequestPayload) => {
        router.post(url, data, { preserveScroll: true, onSuccess: onDone });
    };

    const destroy = () => {
        if (!confirm(`Delete ${selectedIds.length} WordPress user(s)? This deletes them on the live site and cannot be undone.`)) return;
        router.delete('/admin/wordpress-users/bulk-delete', { data: { wordpress_user_ids: selectedIds }, preserveScroll: true, onSuccess: onDone });
    };

    return (
        <div className="bg-muted/50 border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <span className="text-sm font-medium">{selectedIds.length} selected</span>
            <ChangeRoleDialog
                roles={roles}
                onApply={(newRoles) => post('/admin/wordpress-users/bulk-change-role', { wordpress_user_ids: selectedIds, roles: newRoles })}
            />
            <UpdateEmailDialog selectedUsers={selectedUsers} onApply={(updates) => post('/admin/wordpress-users/bulk-update-email', { updates })} />
            <Button size="sm" variant="destructive" onClick={destroy}>
                Delete
            </Button>
            <Button size="sm" variant="ghost" onClick={onDone}>
                Cancel
            </Button>
        </div>
    );
}

export default function WordPressUsersIndex({
    users,
    websites,
    roles,
    filters,
}: {
    users: Paginated<WordPressUserRow>;
    websites: Website[];
    roles: string[];
    filters: { website_id?: string; role?: string; search?: string };
}) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [syncing, setSyncing] = useState(false);
    const { flash } = usePage<SharedData>().props;

    const apply = (params: Record<string, string | undefined>) => {
        setSelectedIds([]);
        router.get(
            '/admin/wordpress-users',
            Object.fromEntries(Object.entries({ ...filters, ...params }).filter(([, value]) => value && value !== ALL)) as Record<string, string>,
            { preserveState: true, preserveScroll: true },
        );
    };

    const syncAll = () => {
        setSyncing(true);
        router.post('/admin/wordpress-users/sync', {}, { preserveScroll: true, onFinish: () => setSyncing(false) });
    };

    const allSelected = users.data.length > 0 && selectedIds.length === users.data.length;

    const toggleAll = () => {
        setSelectedIds(allSelected ? [] : users.data.map((user) => user.id));
    };

    const toggleOne = (id: number) => {
        setSelectedIds((current) => (current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id]));
    };

    const selectedUsers = users.data.filter((user) => selectedIds.includes(user.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="WordPress Users" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">WordPress user management</h1>
                    <span className="text-muted-foreground text-sm">{users.total} users</span>
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={syncAll} disabled={syncing}>
                            <RefreshCw className={`mr-1 size-4 ${syncing ? 'animate-spin' : ''}`} />
                            Sync all sites
                        </Button>
                        <AddUserDialog websites={websites} roles={roles} />
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Select value={filters.website_id ?? ALL} onValueChange={(value) => apply({ website_id: value })}>
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="All websites" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All websites</SelectItem>
                            {websites.map((website) => (
                                <SelectItem key={website.id} value={website.id.toString()}>
                                    {website.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.role ?? ALL} onValueChange={(value) => apply({ role: value })}>
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All roles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All roles</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role} value={role} className="capitalize">
                                    {role}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Input
                        placeholder="Search username or email…"
                        defaultValue={filters.search ?? ''}
                        className="w-64"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') apply({ search: e.currentTarget.value });
                        }}
                        onBlur={(e) => apply({ search: e.currentTarget.value })}
                    />
                </div>

                {flash.bulkResults && flash.bulkResults.length > 0 && (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border space-y-1 rounded-xl border p-3 text-sm">
                        <p className="font-medium">Last bulk action results</p>
                        {flash.bulkResults
                            .filter((r) => r.status !== 'ok')
                            .map((r, i) => (
                                <p key={i} className="text-destructive text-xs">
                                    {r.website ?? `#${r.id}`}: {r.error ?? 'failed'}
                                </p>
                            ))}
                        {flash.bulkResults.every((r) => r.status === 'ok') && <p className="text-muted-foreground text-xs">All succeeded.</p>}
                    </div>
                )}

                {selectedIds.length > 0 && (
                    <BulkActionBar selectedIds={selectedIds} selectedUsers={selectedUsers} roles={roles} onDone={() => setSelectedIds([])} />
                )}

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="w-10 p-3">
                                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all" />
                                </th>
                                <th className="p-3 font-medium">Website</th>
                                <th className="p-3 font-medium">Username</th>
                                <th className="p-3 font-medium">Email</th>
                                <th className="p-3 font-medium">Roles</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3">
                                        <Checkbox
                                            checked={selectedIds.includes(user.id)}
                                            onCheckedChange={() => toggleOne(user.id)}
                                            aria-label={`Select ${user.username}`}
                                        />
                                    </td>
                                    <td className="p-3">
                                        <div className="font-medium">{user.website.name}</div>
                                        <div className="text-muted-foreground text-xs">{user.website.domain}</div>
                                    </td>
                                    <td className="p-3">{user.display_name ?? user.username}</td>
                                    <td className="p-3">{user.email ?? '—'}</td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.map((role) => (
                                                <Badge key={role} variant="secondary" className="capitalize">
                                                    {role}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-6 text-center">
                                        No WordPress users found. Connect a site's credentials and sync to populate this list.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {users.current_page} of {users.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!users.prev_page_url}
                                onClick={() =>
                                    users.prev_page_url && router.get(users.prev_page_url, {}, { preserveState: true, preserveScroll: true })
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!users.next_page_url}
                                onClick={() =>
                                    users.next_page_url && router.get(users.next_page_url, {}, { preserveState: true, preserveScroll: true })
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
