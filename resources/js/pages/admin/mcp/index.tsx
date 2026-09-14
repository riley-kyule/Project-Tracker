import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Plug, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'MCP Connector', href: '/admin/mcp' }];

type TokenRow = { id: number; name: string; last_used_at: string | null; created_at: string };
type OAuthUrls = { authorize: string; token: string; scope: string };
type OAuthClientRow = { id: number; name: string; client_id: string; redirect_uri: string; last_used_at: string | null; created_at: string };

function CopyField({ label, value }: { label: string; value: string }) {
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            toast.success(`${label} copied.`);
        } catch {
            toast.error('Could not copy automatically — select and copy it manually instead.');
        }
    };

    return (
        <div className="grid gap-1">
            <Label className="text-muted-foreground text-xs font-normal">{label}</Label>
            <div className="flex items-center gap-2">
                <code className="bg-muted flex-1 rounded px-2 py-1.5 text-xs break-all select-all">{value}</code>
                <Button type="button" size="sm" variant="outline" onClick={copy}>
                    <Copy className="mr-1 size-3.5" /> Copy
                </Button>
            </div>
        </div>
    );
}

function NewOAuthClientAlert({ client }: { client: { clientId: string; clientSecret: string } }) {
    return (
        <Alert>
            <AlertTitle>Your new connector's Client ID and Secret</AlertTitle>
            <AlertDescription className="space-y-2">
                <p>The secret is shown once and can't be recovered — copy both into the AI's connector settings now.</p>
                <CopyField label="Client ID" value={client.clientId} />
                <CopyField label="Client Secret" value={client.clientSecret} />
            </AlertDescription>
        </Alert>
    );
}

function RegisterOAuthClientForm() {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', redirect_uri: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/mcp/oauth-clients', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-end">
            <div className="grid gap-1.5">
                <Label htmlFor="oauth-client-name">Name</Label>
                <Input
                    id="oauth-client-name"
                    placeholder="e.g. ChatGPT"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="oauth-client-redirect">Redirect URL (from the AI's own setup screen)</Label>
                <Input
                    id="oauth-client-redirect"
                    type="url"
                    placeholder="https://chatgpt.com/connector/oauth/..."
                    value={data.redirect_uri}
                    onChange={(e) => setData('redirect_uri', e.target.value)}
                    required
                />
                <InputError message={errors.redirect_uri} />
            </div>
            <Button type="submit" disabled={processing}>
                Register
            </Button>
        </form>
    );
}

function OAuthSetup({ urls, clients }: { urls: OAuthUrls; clients: OAuthClientRow[] }) {
    const revoke = (client: OAuthClientRow) => {
        if (!confirm(`Disconnect "${client.name}"? Any token it issued stops working immediately.`)) return;
        router.delete(`/admin/mcp/oauth-clients/${client.id}`, { preserveScroll: true });
    };

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h2 className="mb-1 text-sm font-semibold">Connect via OAuth (e.g. ChatGPT, Claude)</h2>
            <p className="text-muted-foreground mb-3 text-sm">
                For a connector UI that only offers "No Auth" or full OAuth — no plain API key field. Each person registers their own connector below:
                start adding EWMS in the AI's connector settings, paste the redirect/callback URL it shows you, and register it here to get a Client
                ID and Secret to paste back. Approving the connection there logs you into EWMS as normal and issues a token, revocable below or from
                here.
            </p>

            <div className="mb-4 grid gap-3 sm:grid-cols-2">
                <CopyField label="Auth URL" value={urls.authorize} />
                <CopyField label="Token URL" value={urls.token} />
            </div>

            <RegisterOAuthClientForm />

            <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-muted-foreground text-left">
                        <tr>
                            <th className="py-1 pr-3">Name</th>
                            <th className="py-1 pr-3">Client ID</th>
                            <th className="py-1 pr-3">Last used</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {clients.map((client) => (
                            <tr key={client.id} className="border-t">
                                <td className="py-1.5 pr-3 font-medium">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Plug className="text-muted-foreground size-3.5" />
                                        {client.name}
                                    </span>
                                </td>
                                <td className="text-muted-foreground py-1.5 pr-3 font-mono text-xs">{client.client_id}</td>
                                <td className="text-muted-foreground py-1.5 pr-3">
                                    {client.last_used_at ? fmtDateTime(client.last_used_at) : 'Never'}
                                </td>
                                <td className="py-1.5 text-right">
                                    <Button variant="ghost" size="sm" onClick={() => revoke(client)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {clients.length === 0 && (
                            <tr>
                                <td colSpan={4} className="text-muted-foreground py-4 text-center">
                                    No connectors registered yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function NewTokenAlert({ token }: { token: string }) {
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(token);
            toast.success('Copied to clipboard.');
        } catch {
            toast.error('Could not copy automatically — copy it manually instead.');
        }
    };

    return (
        <Alert>
            <AlertTitle>Your new token</AlertTitle>
            <AlertDescription className="space-y-2">
                <p>This is shown once and can't be recovered — copy it into your AI's connector settings now.</p>
                <div className="flex items-center gap-2">
                    <code className="bg-muted flex-1 rounded px-2 py-1 text-xs break-all">{token}</code>
                    <Button type="button" size="sm" variant="outline" onClick={copy}>
                        <Copy className="mr-1 size-3.5" /> Copy
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}

function NewTokenForm() {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/mcp', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex items-end gap-2">
            <div className="grid gap-1.5">
                <Label htmlFor="token-name">Token name</Label>
                <Input
                    id="token-name"
                    placeholder="e.g. Claude, ChatGPT"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="w-56"
                    required
                />
                <InputError message={errors.name} />
            </div>
            <Button type="submit" disabled={processing}>
                Generate token
            </Button>
        </form>
    );
}

export default function McpIndex({
    tokens,
    endpoint,
    oauthUrls,
    oauthClients,
}: {
    tokens: TokenRow[];
    endpoint: string;
    oauthUrls: OAuthUrls;
    oauthClients: OAuthClientRow[];
}) {
    const { flash } = usePage<SharedData>().props;

    const revoke = (token: TokenRow) => {
        if (!confirm(`Revoke "${token.name}"? Anything still using it will stop working immediately.`)) return;
        router.delete(`/admin/mcp/${token.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="MCP Connector" />
            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">MCP Connector</h1>
                    <p className="text-muted-foreground text-sm">
                        Connect Claude or ChatGPT to EWMS so it can pull company numbers and summarize them for you — headcount, task and ticket
                        status, leave, payroll totals, and traffic. Read-only, and never a per-employee salary figure.
                    </p>
                </div>

                {flash.newToken && <NewTokenAlert token={flash.newToken} />}
                {flash.newOAuthClient && <NewOAuthClientAlert client={flash.newOAuthClient} />}

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Setup with a plain token</h2>
                    <ol className="text-muted-foreground list-inside list-decimal space-y-1 text-sm">
                        <li>
                            Generate a token below and add EWMS as a connector in Claude or ChatGPT with endpoint{' '}
                            <code className="bg-muted rounded px-1 py-0.5 text-xs">{endpoint}</code>.
                        </li>
                        <li>Use the token as a Bearer token (Authorization header) — most connector setup screens call this an API key.</li>
                        <li>Ask it something like "summarize this week's tickets and overdue tasks."</li>
                    </ol>
                </div>

                <OAuthSetup urls={oauthUrls} clients={oauthClients} />

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Tokens</h2>
                    <NewTokenForm />
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground text-left">
                                <tr>
                                    <th className="py-1 pr-3">Name</th>
                                    <th className="py-1 pr-3">Created</th>
                                    <th className="py-1 pr-3">Last used</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {tokens.map((token) => (
                                    <tr key={token.id} className="border-t">
                                        <td className="py-1.5 pr-3 font-medium">
                                            <span className="inline-flex items-center gap-1.5">
                                                <KeyRound className="text-muted-foreground size-3.5" />
                                                {token.name}
                                            </span>
                                        </td>
                                        <td className="py-1.5 pr-3">{fmtDateTime(token.created_at)}</td>
                                        <td className="text-muted-foreground py-1.5 pr-3">
                                            {token.last_used_at ? fmtDateTime(token.last_used_at) : 'Never'}
                                        </td>
                                        <td className="py-1.5 text-right">
                                            <Button variant="ghost" size="sm" onClick={() => revoke(token)}>
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {tokens.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground py-4 text-center">
                                            No tokens yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
