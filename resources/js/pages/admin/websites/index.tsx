import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plug, Plus, RefreshCw, Trash2, Users as UsersIcon, X } from 'lucide-react';
import { useState } from 'react';

type Option = { id: number; name: string };

type AssignedUser = Option & { pivot: { id: number; team: string } };

type WordPressCredential = {
    id: number;
    wp_username: string;
    wp_app_password_set: boolean;
    status: 'unverified' | 'ok' | 'error';
    last_verified_at: string | null;
    last_synced_at: string | null;
    last_error: string | null;
};

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
    assigned_users: AssignedUser[];
    wordpress_credential: WordPressCredential | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Websites', href: '/admin/websites' }];

const NONE = 'none';

const TEAM_LABELS: Record<string, string> = {
    marketing: 'Marketing',
    customer_service: 'Customer Service',
};

const WP_STATUS_VARIANT: Record<WordPressCredential['status'], 'secondary' | 'default' | 'destructive'> = {
    unverified: 'secondary',
    ok: 'default',
    error: 'destructive',
};

function AssignmentsDialog({ website, users, teams }: { website: WebsiteRow; users: Option[]; teams: string[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: '',
        team: teams[0] ?? 'marketing',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/websites/${website.id}/assignments`, {
            preserveScroll: true,
            onSuccess: () => reset('user_id'),
        });
    };

    const remove = (assignmentId: number) => {
        router.delete(`/admin/website-assignments/${assignmentId}`, { preserveScroll: true });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" aria-label={`Manage members for ${website.name}`}>
                    <UsersIcon className="mr-1 size-4" />
                    {website.assigned_users.length}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assigned members — {website.name}</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        {website.assigned_users.length === 0 && <p className="text-muted-foreground text-sm">No members assigned yet.</p>}
                        {website.assigned_users.map((user) => (
                            <div key={`${user.id}-${user.pivot.team}`} className="flex items-center justify-between rounded-md border p-2 text-sm">
                                <div className="flex items-center gap-2">
                                    <span>{user.name}</span>
                                    <Badge variant="secondary">{TEAM_LABELS[user.pivot.team] ?? user.pivot.team}</Badge>
                                </div>
                                <Button variant="ghost" size="sm" aria-label={`Remove ${user.name}`} onClick={() => remove(user.pivot.id)}>
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                    <form onSubmit={submit} className="flex items-end gap-2 border-t pt-4">
                        <div className="grid flex-1 gap-2">
                            <Label>Member</Label>
                            <Select value={data.user_id} onValueChange={(value) => setData('user_id', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a user" />
                                </SelectTrigger>
                                <SelectContent>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={user.id.toString()}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Team</Label>
                            <Select value={data.team} onValueChange={(value) => setData('team', value)}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {teams.map((team) => (
                                        <SelectItem key={team} value={team}>
                                            {TEAM_LABELS[team] ?? team}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit" disabled={processing || !data.user_id}>
                            Add
                        </Button>
                    </form>
                    <InputError message={errors.user_id ?? errors.team} />
                </div>
            </DialogContent>
        </Dialog>
    );
}

function WebsiteDialog({
    website,
    countries,
    departments,
    users,
    canManageWordPress,
    trigger,
}: {
    website?: WebsiteRow;
    countries: Option[];
    departments: Option[];
    users: Option[];
    canManageWordPress: boolean;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, transform, reset } = useForm({
        name: website?.name ?? '',
        domain: website?.domain ?? '',
        country_id: website?.country?.id.toString() ?? NONE,
        status: website?.status ?? 'active',
        responsible_department_id: website?.responsible_department?.id.toString() ?? NONE,
        responsible_user_id: website?.responsible_user?.id.toString() ?? NONE,
        ga4_property_id: website?.ga4_property_id ?? '',
        gsc_property: website?.gsc_property ?? '',
        wp_username: website?.wordpress_credential?.wp_username ?? '',
        wp_app_password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            country_id: form.country_id === NONE ? null : Number(form.country_id),
            responsible_department_id: form.responsible_department_id === NONE ? null : Number(form.responsible_department_id),
            responsible_user_id: form.responsible_user_id === NONE ? null : Number(form.responsible_user_id),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset('wp_app_password');
            },
        };
        if (website) {
            patch(`/admin/websites/${website.id}`, options);
        } else {
            post('/admin/websites', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
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
                        <Input id="site-domain" placeholder="example.com" value={data.domain} onChange={(e) => setData('domain', e.target.value)} />
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

                    {canManageWordPress && (
                        <div className="space-y-4 border-t pt-4">
                            <h3 className="text-sm font-semibold">WordPress access</h3>
                            <div className="grid gap-2">
                                <Label htmlFor="wp-username">WordPress username</Label>
                                <Input
                                    id="wp-username"
                                    value={data.wp_username}
                                    onChange={(e) => setData('wp_username', e.target.value)}
                                    autoComplete="off"
                                />
                                <InputError message={errors.wp_username} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="wp-app-password">Application password</Label>
                                <Input
                                    id="wp-app-password"
                                    type="password"
                                    autoComplete="off"
                                    placeholder={website?.wordpress_credential?.wp_app_password_set ? 'Unchanged (already set)' : ''}
                                    value={data.wp_app_password}
                                    onChange={(e) => setData('wp_app_password', e.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">
                                    Generate one from the site's WordPress admin under Users → Profile → Application Passwords, using an account with
                                    Administrator capabilities. Leave both fields blank to skip WordPress access for this site.
                                </p>
                                <InputError message={errors.wp_app_password} />
                            </div>
                        </div>
                    )}

                    <Button type="submit" disabled={processing}>
                        {website ? 'Save changes' : 'Add website'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function WordPressCell({ website }: { website: WebsiteRow }) {
    const [busy, setBusy] = useState(false);
    const credential = website.wordpress_credential;

    const test = () => {
        setBusy(true);
        router.post(`/admin/websites/${website.id}/wordpress-credential/test`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const sync = () => {
        router.post(`/admin/websites/${website.id}/wordpress-credential/sync`, {}, { preserveScroll: true });
    };

    const destroy = () => {
        if (!confirm(`Remove WordPress credentials for ${website.name}? Its synced users will also be cleared from EWMS.`)) return;
        router.delete(`/admin/websites/${website.id}/wordpress-credential`, { preserveScroll: true });
    };

    if (!credential) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex items-center gap-2">
            <div>
                <Badge variant={WP_STATUS_VARIANT[credential.status]}>{credential.status}</Badge>
                {credential.last_synced_at && (
                    <div className="text-muted-foreground mt-0.5 text-xs">Synced {new Date(credential.last_synced_at).toLocaleDateString()}</div>
                )}
                {credential.last_error && (
                    <div className="text-destructive mt-0.5 max-w-40 truncate text-xs" title={credential.last_error}>
                        {credential.last_error}
                    </div>
                )}
            </div>
            <div className="flex items-center gap-0.5">
                <Button variant="ghost" size="sm" aria-label={`Test connection for ${website.name}`} onClick={test} disabled={busy}>
                    <Plug className="size-4" />
                </Button>
                <Button variant="ghost" size="sm" aria-label={`Sync now for ${website.name}`} onClick={sync}>
                    <RefreshCw className="size-4" />
                </Button>
                <Button variant="ghost" size="sm" aria-label={`Remove WordPress credentials for ${website.name}`} onClick={destroy}>
                    <Trash2 className="text-destructive size-4" />
                </Button>
            </div>
        </div>
    );
}

export default function WebsitesIndex({
    websites,
    countries,
    departments,
    users,
    teams,
    canManage,
    canManageWordPress,
}: {
    websites: WebsiteRow[];
    countries: Option[];
    departments: Option[];
    users: Option[];
    teams: string[];
    canManage: boolean;
    canManageWordPress: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Websites" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Website registry</h1>
                        {canManageWordPress && (
                            <p className="text-muted-foreground text-sm">
                                Add each site's domain and an Application Password here to enable staff access management via{' '}
                                <Link href="/admin/wordpress-users" className="text-brand-600 dark:text-brand-400 hover:underline">
                                    WordPress Users
                                </Link>
                                .
                            </p>
                        )}
                    </div>
                    {canManage && (
                        <WebsiteDialog
                            countries={countries}
                            departments={departments}
                            users={users}
                            canManageWordPress={canManageWordPress}
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
                                <th className="p-3 font-medium">Members</th>
                                {canManageWordPress && <th className="p-3 font-medium">WordPress</th>}
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {websites.map((website) => (
                                <tr key={website.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-medium">
                                        {website.name}
                                        <div className="text-muted-foreground text-xs font-normal">{website.domain}</div>
                                    </td>
                                    <td className="p-3">{website.country?.name ?? '—'}</td>
                                    <td className="p-3">{website.responsible_user?.name ?? website.responsible_department?.name ?? '—'}</td>
                                    <td className="p-3 text-xs">
                                        {website.ga4_property_id ? 'GA4 ✓' : 'GA4 —'} / {website.gsc_property ? 'GSC ✓' : 'GSC —'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={website.status === 'active' ? 'default' : 'secondary'}>{website.status}</Badge>
                                    </td>
                                    <td className="p-3">
                                        {canManage ? (
                                            <AssignmentsDialog website={website} users={users} teams={teams} />
                                        ) : (
                                            website.assigned_users.length
                                        )}
                                    </td>
                                    {canManageWordPress && (
                                        <td className="p-3">
                                            <WordPressCell website={website} />
                                        </td>
                                    )}
                                    {canManage && (
                                        <td className="p-3 text-right">
                                            <WebsiteDialog
                                                website={website}
                                                countries={countries}
                                                departments={departments}
                                                users={users}
                                                canManageWordPress={canManageWordPress}
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
                                    <td colSpan={8} className="text-muted-foreground p-6 text-center">
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
