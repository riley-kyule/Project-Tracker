import InputError from '@/components/input-error';
import { SortableHeader, type SortState } from '@/components/sortable-header';
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
import { Pencil, Plug, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Credential = {
    id: number;
    wp_username: string;
    wp_app_password_set: boolean;
    status: 'unverified' | 'ok' | 'error';
    last_verified_at: string | null;
    last_synced_at: string | null;
    last_error: string | null;
};

type Site = { id: number; name: string; domain: string | null; credential: Credential | null };

type WordPressUserRow = {
    id: number;
    site: { id: number; name: string; domain: string | null };
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

const STATUS_VARIANT: Record<Credential['status'], 'secondary' | 'default' | 'destructive'> = {
    unverified: 'secondary',
    ok: 'default',
    error: 'destructive',
};

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

function SiteDialog({ site, trigger }: { site?: Site; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: site?.name ?? '',
        domain: site?.domain ?? '',
        wp_username: site?.credential?.wp_username ?? '',
        wp_app_password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset('wp_app_password');
            },
        };
        if (site) {
            patch(`/admin/wordpress-users/sites/${site.id}`, options);
        } else {
            post('/admin/wordpress-users/sites', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{site ? `Edit ${site.name}` : 'Connect a website'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="site-name">Name</Label>
                        <Input id="site-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="site-domain">Domain</Label>
                        <Input
                            id="site-domain"
                            placeholder="example.com"
                            value={data.domain}
                            onChange={(e) => setData('domain', e.target.value)}
                            required
                        />
                        <InputError message={errors.domain} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="wp-username">WordPress username</Label>
                        <Input
                            id="wp-username"
                            value={data.wp_username}
                            onChange={(e) => setData('wp_username', e.target.value)}
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.wp_username} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="wp-app-password">Application password</Label>
                        <Input
                            id="wp-app-password"
                            type="password"
                            autoComplete="off"
                            placeholder={site?.credential?.wp_app_password_set ? 'Unchanged (already set)' : ''}
                            value={data.wp_app_password}
                            onChange={(e) => setData('wp_app_password', e.target.value)}
                            required={!site}
                        />
                        <p className="text-muted-foreground text-xs">
                            Generate one from the site's WordPress admin under Users → Profile → Application Passwords, using an account with
                            Administrator capabilities.
                        </p>
                        <InputError message={errors.wp_app_password} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {site ? 'Save changes' : 'Connect & sync'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SitesPanel({ sites }: { sites: Site[] }) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    const term = search.trim().toLowerCase();
    const filteredSites =
        term === '' ? sites : sites.filter((site) => site.name.toLowerCase().includes(term) || (site.domain ?? '').toLowerCase().includes(term));

    const test = (site: Site) => {
        setBusyId(site.id);
        router.post(`/admin/wordpress-users/sites/${site.id}/test`, {}, { preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    const sync = (site: Site) => {
        router.post(`/admin/wordpress-users/sites/${site.id}/sync`, {}, { preserveScroll: true });
    };

    const destroy = (site: Site) => {
        if (!confirm(`Disconnect ${site.name}? This removes its credentials and clears its synced users from EWMS.`)) return;
        router.delete(`/admin/wordpress-users/sites/${site.id}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">Connected sites</h2>
                <div className="flex items-center gap-2">
                    <Input placeholder="Search sites…" value={search} onChange={(e) => setSearch(e.target.value)} className="h-8 w-48 text-xs" />
                    <SiteDialog
                        trigger={
                            <Button size="sm">
                                <Plus className="mr-1 size-4" /> Connect a website
                            </Button>
                        }
                    />
                </div>
            </div>
            <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                            <th className="p-3 font-medium">Site</th>
                            <th className="p-3 font-medium">WP username</th>
                            <th className="p-3 font-medium">Status</th>
                            <th className="p-3 font-medium">Last synced</th>
                            <th className="p-3 font-medium">Last error</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {filteredSites.map((site) => (
                            <tr key={site.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                <td className="p-3">
                                    <div className="font-medium">{site.name}</div>
                                    <div className="text-muted-foreground text-xs">{site.domain}</div>
                                </td>
                                <td className="p-3">{site.credential?.wp_username ?? '—'}</td>
                                <td className="p-3">
                                    {site.credential ? <Badge variant={STATUS_VARIANT[site.credential.status]}>{site.credential.status}</Badge> : '—'}
                                </td>
                                <td className="p-3 text-xs">
                                    {site.credential?.last_synced_at ? new Date(site.credential.last_synced_at).toLocaleString() : '—'}
                                </td>
                                <td className="max-w-xs truncate p-3 font-mono text-xs" title={site.credential?.last_error ?? undefined}>
                                    {site.credential?.last_error ?? '—'}
                                </td>
                                <td className="p-3">
                                    <div className="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label={`Test connection for ${site.name}`}
                                            onClick={() => test(site)}
                                            disabled={busyId === site.id}
                                        >
                                            <Plug className="size-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" aria-label={`Sync now for ${site.name}`} onClick={() => sync(site)}>
                                            <RefreshCw className="size-4" />
                                        </Button>
                                        <SiteDialog
                                            site={site}
                                            trigger={
                                                <Button variant="ghost" size="sm" aria-label={`Edit ${site.name}`}>
                                                    <Pencil className="size-4" />
                                                </Button>
                                            }
                                        />
                                        <Button variant="ghost" size="sm" aria-label={`Disconnect ${site.name}`} onClick={() => destroy(site)}>
                                            <Trash2 className="text-destructive size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {filteredSites.length === 0 && (
                            <tr>
                                <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                    {sites.length === 0
                                        ? 'No sites connected yet. Connect a website above to start managing its staff.'
                                        : 'No sites match this search.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function AddUserDialog({ sites, roles }: { sites: Site[]; roles: string[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        site_ids: [] as number[],
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

    const toggleSite = (id: number) => {
        setData('site_ids', data.site_ids.includes(id) ? data.site_ids.filter((s) => s !== id) : [...data.site_ids, id]);
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
                        <Label>Sites</Label>
                        <div className="max-h-40 space-y-1.5 overflow-y-auto rounded-md border p-2">
                            {sites.map((site) => (
                                <label key={site.id} className="flex items-center gap-1.5 text-sm">
                                    <Checkbox checked={data.site_ids.includes(site.id)} onCheckedChange={() => toggleSite(site.id)} />
                                    {site.name}
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.site_ids} />
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
                    <Button type="submit" disabled={processing || data.site_ids.length === 0}>
                        Add to {data.site_ids.length || 0} site(s)
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ChangeRoleDialog({ roles, onApply }: { roles: string[]; onApply: (roles: string[], onFinish: () => void) => void }) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);

    const apply = () => {
        setProcessing(true);
        onApply(selected, () => setProcessing(false));
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
    onApply: (updates: { id: number; email: string }[], onFinish: () => void) => void;
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
        onApply(
            selectedUsers.map((u) => ({ id: u.id, email: emails[u.id] ?? '' })),
            () => setProcessing(false),
        );
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
                                {user.username} · {user.site.name}
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
    totalMatching,
}: {
    selectedIds: number[];
    selectedUsers: WordPressUserRow[];
    roles: string[];
    onDone: () => void;
    /** Rows matching the current filters across every page — shown only when it exceeds what's selectable on this one page, since that's exactly the gap that makes "select all" mean less than it looks like it means. */
    totalMatching: number;
}) {
    const post = (url: string, data: RequestPayload, onFinish: () => void) => {
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'That request was rejected — check your selection and try again.'),
            onFinish,
        });
    };

    const destroy = () => {
        if (!confirm(`Delete ${selectedIds.length} WordPress user(s)? This deletes them on the live site and cannot be undone.`)) return;
        router.delete('/admin/wordpress-users/bulk-delete', {
            data: { wordpress_user_ids: selectedIds },
            preserveScroll: true,
            onSuccess: onDone,
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'That request was rejected — check your selection and try again.'),
        });
    };

    return (
        <div className="bg-muted/50 border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <span className="text-sm font-medium">
                {selectedIds.length} selected
                {totalMatching > selectedIds.length && (
                    <span className="text-muted-foreground font-normal"> of {totalMatching} matching — not everything matching is selected</span>
                )}
            </span>
            <ChangeRoleDialog
                roles={roles}
                onApply={(newRoles, onFinish) =>
                    post('/admin/wordpress-users/bulk-change-role', { wordpress_user_ids: selectedIds, roles: newRoles }, onFinish)
                }
            />
            <UpdateEmailDialog
                selectedUsers={selectedUsers}
                onApply={(updates, onFinish) => post('/admin/wordpress-users/bulk-update-email', { updates }, onFinish)}
            />
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
    sites,
    roles,
    filters,
    sort: sortColumn,
    direction,
}: {
    users: Paginated<WordPressUserRow>;
    sites: Site[];
    roles: string[];
    filters: { site_id?: string; role?: string; search?: string };
    sort: string | null;
    direction: 'asc' | 'desc';
}) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [syncing, setSyncing] = useState(false);
    const { flash } = usePage<SharedData>().props;
    const sort: SortState = { column: sortColumn, direction };

    const connectedSites = sites.filter((s) => s.credential);

    const apply = (params: Record<string, string | undefined>) => {
        setSelectedIds([]);
        router.get(
            '/admin/wordpress-users',
            Object.fromEntries(
                Object.entries({ ...filters, sort: sortColumn ?? undefined, direction, ...params }).filter(([, value]) => value && value !== ALL),
            ) as Record<string, string>,
            { preserveState: true, preserveScroll: true },
        );
    };

    const onSort = (column: string) => {
        apply({ sort: column, direction: sort.column === column && sort.direction === 'asc' ? 'desc' : 'asc' });
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
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">WordPress Users</h1>
                    <p className="text-muted-foreground text-sm">
                        Connect WordPress sites with an Application Password, then list, filter, and manage staff accounts across all of them.
                    </p>
                </div>

                <SitesPanel sites={sites} />

                <div className="flex flex-wrap items-center gap-2 border-t pt-6">
                    <h2 className="text-sm font-semibold">Users</h2>
                    <span className="text-muted-foreground text-sm">{users.total} total</span>
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={syncAll} disabled={syncing || connectedSites.length === 0}>
                            <RefreshCw className={`mr-1 size-4 ${syncing ? 'animate-spin' : ''}`} />
                            Sync all sites
                        </Button>
                        <AddUserDialog sites={connectedSites} roles={roles} />
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Select value={filters.site_id ?? ALL} onValueChange={(value) => apply({ site_id: value })}>
                        <SelectTrigger className="w-56" aria-label="Filter by site">
                            <SelectValue placeholder="All sites" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All sites</SelectItem>
                            {sites.map((site) => (
                                <SelectItem key={site.id} value={site.id.toString()}>
                                    {site.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.role ?? ALL} onValueChange={(value) => apply({ role: value })}>
                        <SelectTrigger className="w-44" aria-label="Filter by role">
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
                                    {r.site ?? `#${r.id}`}: {r.error ?? 'failed'}
                                </p>
                            ))}
                        {flash.bulkResults.every((r) => r.status === 'ok') && <p className="text-muted-foreground text-xs">All succeeded.</p>}
                    </div>
                )}

                {selectedIds.length > 0 && (
                    <BulkActionBar
                        selectedIds={selectedIds}
                        selectedUsers={selectedUsers}
                        roles={roles}
                        onDone={() => setSelectedIds([])}
                        totalMatching={users.total}
                    />
                )}

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="w-10 p-3">
                                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all on this page" />
                                </th>
                                <th className="p-3 font-medium">Site</th>
                                <SortableHeader column="username" sort={sort} onSort={onSort} className="p-3">
                                    Username
                                </SortableHeader>
                                <SortableHeader column="email" sort={sort} onSort={onSort} className="p-3">
                                    Email
                                </SortableHeader>
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
                                        <div className="font-medium">{user.site.name}</div>
                                        <div className="text-muted-foreground text-xs">{user.site.domain}</div>
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
                                        No WordPress users found. Connect a site above and sync to populate this list.
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
