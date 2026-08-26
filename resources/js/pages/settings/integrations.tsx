import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Integration settings', href: '/settings/integrations' }];

type IntegrationSettings = {
    mail_mailer: string | null;
    mail_host: string | null;
    mail_port: number | null;
    mail_username: string | null;
    mail_password_set: boolean;
    mail_encryption: string | null;
    mail_from_address: string | null;
    mail_from_name: string | null;
    epe_api_url: string | null;
    epe_site_key: string | null;
    backup_frequency: string | null;
    backup_time: string | null;
    backup_retention_count: number;
    google_drive_connected_email: string | null;
};

type BackupRun = {
    frequency: string;
    status: 'running' | 'succeeded' | 'failed';
    started_at: string;
    finished_at: string | null;
    error_message: string | null;
};

const NONE = 'none';

const backupStatusVariant: Record<BackupRun['status'], 'default' | 'secondary' | 'destructive'> = {
    running: 'secondary',
    succeeded: 'default',
    failed: 'destructive',
};

function LastBackupRun({ run }: { run: BackupRun | null }) {
    if (!run) {
        return <p className="text-muted-foreground text-sm">No backup has run yet.</p>;
    }

    return (
        <div className="flex flex-wrap items-center gap-2 text-sm">
            <Badge variant={backupStatusVariant[run.status]} className="capitalize">
                {run.status}
            </Badge>
            <span className="text-muted-foreground capitalize">{run.frequency}</span>
            <span className="text-muted-foreground">{new Date(run.started_at).toLocaleString()}</span>
            {run.status === 'failed' && run.error_message && <span className="text-destructive">{run.error_message}</span>}
        </div>
    );
}

export default function IntegrationSettingsPage({ settings, lastBackupRun }: { settings: IntegrationSettings; lastBackupRun: BackupRun | null }) {
    const { data, setData, patch, processing, errors, recentlySuccessful, transform } = useForm({
        mail_mailer: settings.mail_mailer ?? 'log',
        mail_host: settings.mail_host ?? '',
        mail_port: settings.mail_port?.toString() ?? '',
        mail_username: settings.mail_username ?? '',
        mail_password: '',
        mail_encryption: settings.mail_encryption ?? NONE,
        mail_from_address: settings.mail_from_address ?? '',
        mail_from_name: settings.mail_from_name ?? '',
        epe_api_url: settings.epe_api_url ?? '',
        epe_site_key: settings.epe_site_key ?? '',
        backup_frequency: settings.backup_frequency ?? NONE,
        backup_time: settings.backup_time?.slice(0, 5) ?? '',
        backup_retention_count: settings.backup_retention_count.toString(),
    });

    const driveError = usePage<{ errors: Record<string, string> }>().props.errors.drive;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            mail_port: form.mail_port === '' ? null : Number(form.mail_port),
            mail_encryption: form.mail_encryption === NONE ? null : form.mail_encryption,
            backup_frequency: form.backup_frequency === NONE ? null : form.backup_frequency,
            backup_time: form.backup_time === '' ? null : form.backup_time,
            backup_retention_count: Number(form.backup_retention_count),
        }));
        patch('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => setData('mail_password', ''),
        });
    };

    const disconnectDrive = () => {
        router.delete('/settings/integrations/google-drive', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integration settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Integrations"
                        description="Configure email delivery, browser push, and backups without touching the server"
                    />

                    <div className="space-y-4 border-b pb-6">
                        <h3 className="text-sm font-semibold">Backups</h3>
                        {settings.google_drive_connected_email ? (
                            <div className="flex flex-wrap items-center gap-3">
                                <p className="text-sm">
                                    Connected to <span className="font-medium">{settings.google_drive_connected_email}</span>
                                </p>
                                <Button type="button" variant="outline" size="sm" onClick={disconnectDrive}>
                                    Disconnect
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-sm">
                                    Connect a Google account to store automatic backups in its Drive. Use a company account dedicated to backups, not
                                    a personal one.
                                </p>
                                <Button asChild size="sm">
                                    <a href="/settings/integrations/google-drive/connect">Connect Google Drive</a>
                                </Button>
                                {driveError && <p className="text-destructive text-sm">{driveError}</p>}
                            </div>
                        )}

                        {settings.google_drive_connected_email && (
                            <>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label>Frequency</Label>
                                        <Select value={data.backup_frequency} onValueChange={(value) => setData('backup_frequency', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>Off</SelectItem>
                                                <SelectItem value="daily">Daily</SelectItem>
                                                <SelectItem value="weekly">Weekly</SelectItem>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="backup-time">Time</Label>
                                        <Input
                                            id="backup-time"
                                            type="time"
                                            value={data.backup_time}
                                            onChange={(e) => setData('backup_time', e.target.value)}
                                        />
                                        <InputError message={errors.backup_time} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="backup-retention">Keep last</Label>
                                        <Input
                                            id="backup-retention"
                                            type="number"
                                            min={1}
                                            max={365}
                                            value={data.backup_retention_count}
                                            onChange={(e) => setData('backup_retention_count', e.target.value)}
                                        />
                                        <InputError message={errors.backup_retention_count} />
                                    </div>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Backs up the database and file attachments together. Older backups beyond the count above are deleted from Drive
                                    automatically.
                                </p>
                                <LastBackupRun run={lastBackupRun} />
                            </>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-8">
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Email</h3>
                            <p className="text-muted-foreground text-sm">
                                Leave on Log to keep writing emails to the server log instead of actually sending them.
                            </p>
                            <div className="grid gap-2">
                                <Label>Mailer</Label>
                                <Select value={data.mail_mailer} onValueChange={(value) => setData('mail_mailer', value)}>
                                    <SelectTrigger className="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="log">Log (default)</SelectItem>
                                        <SelectItem value="smtp">SMTP</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {data.mail_mailer === 'smtp' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-host">Host</Label>
                                        <Input id="mail-host" value={data.mail_host} onChange={(e) => setData('mail_host', e.target.value)} />
                                        {errors.mail_host && <p className="text-destructive text-sm">{errors.mail_host}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-port">Port</Label>
                                        <Input
                                            id="mail-port"
                                            type="number"
                                            value={data.mail_port}
                                            onChange={(e) => setData('mail_port', e.target.value)}
                                        />
                                        {errors.mail_port && <p className="text-destructive text-sm">{errors.mail_port}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-username">Username</Label>
                                        <Input
                                            id="mail-username"
                                            value={data.mail_username}
                                            onChange={(e) => setData('mail_username', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-password">Password</Label>
                                        <Input
                                            id="mail-password"
                                            type="password"
                                            placeholder={settings.mail_password_set ? 'Unchanged (already set)' : ''}
                                            value={data.mail_password}
                                            onChange={(e) => setData('mail_password', e.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            {settings.mail_password_set ? 'Leave blank to keep the current password.' : 'No password stored yet.'}
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Encryption</Label>
                                        <Select value={data.mail_encryption} onValueChange={(value) => setData('mail_encryption', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>None</SelectItem>
                                                <SelectItem value="tls">TLS</SelectItem>
                                                <SelectItem value="ssl">SSL</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-from-address">From address</Label>
                                        <Input
                                            id="mail-from-address"
                                            type="email"
                                            value={data.mail_from_address}
                                            onChange={(e) => setData('mail_from_address', e.target.value)}
                                        />
                                        {errors.mail_from_address && <p className="text-destructive text-sm">{errors.mail_from_address}</p>}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mail-from-name">From name</Label>
                                        <Input
                                            id="mail-from-name"
                                            value={data.mail_from_name}
                                            onChange={(e) => setData('mail_from_name', e.target.value)}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="space-y-4 border-t pt-6">
                            <h3 className="text-sm font-semibold">Browser push (Exotic Push Engine)</h3>
                            <p className="text-muted-foreground text-sm">Leave blank to disable push notifications entirely.</p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="epe-api-url">API URL</Label>
                                    <Input
                                        id="epe-api-url"
                                        placeholder="https://push.example.com"
                                        value={data.epe_api_url}
                                        onChange={(e) => setData('epe_api_url', e.target.value)}
                                    />
                                    {errors.epe_api_url && <p className="text-destructive text-sm">{errors.epe_api_url}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="epe-site-key">Site key</Label>
                                    <Input id="epe-site-key" value={data.epe_site_key} onChange={(e) => setData('epe_site_key', e.target.value)} />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
