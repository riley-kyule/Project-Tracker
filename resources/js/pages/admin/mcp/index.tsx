import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'MCP Connector', href: '/admin/mcp' }];

type TokenRow = { id: number; name: string; last_used_at: string | null; created_at: string };

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

export default function McpIndex({ tokens, endpoint }: { tokens: TokenRow[]; endpoint: string }) {
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

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Setup</h2>
                    <ol className="text-muted-foreground list-inside list-decimal space-y-1 text-sm">
                        <li>
                            Generate a token below and add EWMS as a connector in Claude or ChatGPT with endpoint{' '}
                            <code className="bg-muted rounded px-1 py-0.5 text-xs">{endpoint}</code>.
                        </li>
                        <li>Use the token as a Bearer token (Authorization header) — most connector setup screens call this an API key.</li>
                        <li>Ask it something like "summarize this week's tickets and overdue tasks."</li>
                    </ol>
                </div>

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
