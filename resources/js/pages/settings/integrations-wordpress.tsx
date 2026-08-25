import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plug, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Credential = {
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
    credential: Credential | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Integration settings', href: '/settings/integrations' },
    { title: 'WordPress', href: '/settings/integrations/wordpress' },
];

const STATUS_VARIANT: Record<Credential['status'], 'secondary' | 'default' | 'destructive'> = {
    unverified: 'secondary',
    ok: 'default',
    error: 'destructive',
};

function CredentialDialog({ website }: { website: WebsiteRow }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        website_id: website.id,
        wp_username: website.credential?.wp_username ?? '',
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
        if (website.credential) {
            patch(`/settings/integrations/wordpress/${website.credential.id}`, options);
        } else {
            post('/settings/integrations/wordpress', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={website.credential ? 'ghost' : 'outline'} size="sm">
                    {website.credential ? <Pencil className="size-4" /> : 'Add credentials'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{website.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
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
                            placeholder={website.credential?.wp_app_password_set ? 'Unchanged (already set)' : ''}
                            value={data.wp_app_password}
                            onChange={(e) => setData('wp_app_password', e.target.value)}
                            required={!website.credential}
                        />
                        <p className="text-muted-foreground text-xs">
                            Generate one from the site's WordPress admin under Users → Profile → Application Passwords, using an account with
                            Administrator capabilities.
                        </p>
                        <InputError message={errors.wp_app_password} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {website.credential ? 'Save changes' : 'Add credentials'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function IntegrationsWordPress({ websites }: { websites: WebsiteRow[] }) {
    const [syncing, setSyncing] = useState(false);
    const [testingId, setTestingId] = useState<number | null>(null);

    const syncAll = () => {
        setSyncing(true);
        router.post('/admin/wordpress-users/sync', {}, { preserveScroll: true, onFinish: () => setSyncing(false) });
    };

    const test = (credentialId: number) => {
        setTestingId(credentialId);
        router.post(`/settings/integrations/wordpress/${credentialId}/test`, {}, { preserveScroll: true, onFinish: () => setTestingId(null) });
    };

    const sync = (credentialId: number) => {
        router.post(`/settings/integrations/wordpress/${credentialId}/sync`, {}, { preserveScroll: true });
    };

    const destroy = (website: WebsiteRow) => {
        if (!website.credential) return;
        if (!confirm(`Remove WordPress credentials for ${website.name}? Its synced users will also be cleared from EWMS.`)) return;
        router.delete(`/settings/integrations/wordpress/${website.credential.id}`, { preserveScroll: true });
    };

    const connectedCount = websites.filter((w) => w.credential).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="WordPress integration" />
            <SettingsLayout>
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold">WordPress site credentials</h2>
                            <p className="text-muted-foreground text-sm">
                                {connectedCount} of {websites.length} websites connected. Each site needs its own Application Password from an
                                Administrator-capable account.
                            </p>
                        </div>
                        <Button size="sm" variant="outline" onClick={syncAll} disabled={syncing || connectedCount === 0}>
                            <RefreshCw className={`mr-1 size-4 ${syncing ? 'animate-spin' : ''}`} />
                            Sync all
                        </Button>
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                    <th className="p-3 font-medium">Website</th>
                                    <th className="p-3 font-medium">WP username</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 font-medium">Last synced</th>
                                    <th className="p-3 font-medium">Last error</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {websites.map((website) => (
                                    <tr key={website.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                        <td className="p-3">
                                            <div className="font-medium">{website.name}</div>
                                            <div className="text-muted-foreground text-xs">{website.domain ?? '—'}</div>
                                        </td>
                                        <td className="p-3">{website.credential?.wp_username ?? '—'}</td>
                                        <td className="p-3">
                                            {website.credential ? (
                                                <Badge variant={STATUS_VARIANT[website.credential.status]}>{website.credential.status}</Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="p-3 text-xs">
                                            {website.credential?.last_synced_at ? new Date(website.credential.last_synced_at).toLocaleString() : '—'}
                                        </td>
                                        <td className="max-w-xs truncate p-3 font-mono text-xs" title={website.credential?.last_error ?? undefined}>
                                            {website.credential?.last_error ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {website.credential && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Test connection for ${website.name}`}
                                                            onClick={() => test(website.credential!.id)}
                                                            disabled={testingId === website.credential.id}
                                                        >
                                                            <Plug className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Sync now for ${website.name}`}
                                                            onClick={() => sync(website.credential!.id)}
                                                        >
                                                            <RefreshCw className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                                <CredentialDialog website={website} />
                                                {website.credential && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Remove credentials for ${website.name}`}
                                                        onClick={() => destroy(website)}
                                                    >
                                                        <Trash2 className="text-destructive size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {websites.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                            No websites in the registry yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
