import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
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
};

const NONE = 'none';

export default function IntegrationSettingsPage({ settings }: { settings: IntegrationSettings }) {
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
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            mail_port: form.mail_port === '' ? null : Number(form.mail_port),
            mail_encryption: form.mail_encryption === NONE ? null : form.mail_encryption,
        }));
        patch('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => setData('mail_password', ''),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integration settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Integrations"
                        description="Configure email delivery and browser push without touching the server"
                    />

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
                                            {settings.mail_password_set
                                                ? 'Leave blank to keep the current password.'
                                                : 'No password stored yet.'}
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
