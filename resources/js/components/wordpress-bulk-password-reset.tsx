import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { KeyRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type ResetStatus = 'pending' | 'running' | 'succeeded' | 'failed';

type PasswordReset = {
    id: number;
    status: ResetStatus;
    total: number;
    processed: number;
    succeeded: number;
    failed: number;
    failures: { id: number; username: string | null; site: string | null; error: string | null }[] | null;
};

type ResultRow = { id: number; username?: string; site?: string; status: string; error?: string | null; password?: string };

const csrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const json = <T,>(url: string, init?: RequestInit): Promise<T> =>
    fetch(url, { ...init, headers: { Accept: 'application/json', ...init?.headers } }).then((r) => r.json());

/**
 * Resets every synced WordPress staff account's password across every
 * connected site — polled the same way "Deploy now" is (this can genuinely
 * take a while: one live HTTP call per user, per site). Generated passwords
 * are never persisted anywhere in EWMS; this dialog is the only place
 * they're ever shown, and only once — see ResetAllWordPressStaffPasswords.
 */
export function WordPressBulkPasswordReset() {
    const [open, setOpen] = useState(false);
    const [reset, setReset] = useState<PasswordReset | null>(null);
    const [results, setResults] = useState<ResultRow[] | null>(null);
    const [resultsExpired, setResultsExpired] = useState(false);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = () => {
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    };

    const fetchResults = (id: number) => {
        json<{ results: ResultRow[] | null; expired: boolean }>(`/admin/wordpress-users/reset-all-passwords/${id}/results`).then((payload) => {
            setResults(payload.results);
            setResultsExpired(payload.expired);
        });
    };

    const poll = (id: number) => {
        stopPolling();
        pollRef.current = setInterval(() => {
            json<{ reset: PasswordReset }>(`/admin/wordpress-users/reset-all-passwords/${id}`).then((payload) => {
                setReset(payload.reset);
                if (payload.reset.status === 'succeeded' || payload.reset.status === 'failed') {
                    stopPolling();
                    if (payload.reset.status === 'succeeded') fetchResults(id);
                }
            });
        }, 3000);
    };

    useEffect(() => {
        if (!open) return;
        setError(null);
        json<{ reset: PasswordReset | null }>('/admin/wordpress-users/reset-all-passwords/latest').then((payload) => {
            setReset(payload.reset);
            if (!payload.reset) return;
            if (payload.reset.status === 'pending' || payload.reset.status === 'running') poll(payload.reset.id);
            if (payload.reset.status === 'succeeded') fetchResults(payload.reset.id);
        });
        return stopPolling;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const start = () => {
        setStarting(true);
        setError(null);
        setResults(null);
        fetch('/admin/wordpress-users/reset-all-passwords', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        })
            .then(async (response) => {
                const payload = await response.json();
                if (!response.ok) {
                    setError(payload.message ?? 'Could not start the reset.');
                    return;
                }
                setReset(payload.reset);
                poll(payload.reset.id);
            })
            .catch(() => setError('Could not start the reset.'))
            .finally(() => setStarting(false));
    };

    const copy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Copied to clipboard.');
        } catch {
            toast.error('Could not copy automatically — copy it manually instead.');
        }
    };

    const copyAll = () => {
        if (!results) return;
        copy(
            results
                .filter((r) => r.password)
                .map((r) => `${r.username ?? `#${r.id}`} (${r.site ?? 'unknown site'}): ${r.password}`)
                .join('\n'),
        );
    };

    const done = () => {
        if (reset) fetch(`/admin/wordpress-users/reset-all-passwords/${reset.id}/results`, { method: 'DELETE', headers: { 'X-XSRF-TOKEN': csrf() } });
        setOpen(false);
        setResults(null);
    };

    const busy = reset ? reset.status === 'pending' || reset.status === 'running' : false;
    const withPasswords = results?.filter((r) => r.password) ?? [];

    return (
        <Dialog open={open} onOpenChange={(next) => (next ? setOpen(true) : done())}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <KeyRound className="mr-1 size-4" /> Reset ALL passwords
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] max-w-xl flex-col overflow-hidden">
                <DialogHeader className="shrink-0">
                    <DialogTitle>Reset every WordPress staff password</DialogTitle>
                    <DialogDescription>
                        Generates a new strong password for every synced user across every connected site and pushes it live immediately. This can
                        take a while — one HTTP call per user, per site.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto py-1">
                    {error && <p className="text-destructive text-sm">{error}</p>}

                    {reset && (
                        <div className="space-y-2 text-sm">
                            <p>
                                Status: <span className="font-medium capitalize">{reset.status}</span>
                                {reset.total > 0 && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        — {reset.processed} of {reset.total} processed ({reset.succeeded} ok
                                        {reset.failed > 0 ? `, ${reset.failed} failed` : ''})
                                    </span>
                                )}
                            </p>
                            {reset.total > 0 && (
                                <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                    <div
                                        className="bg-primary h-full transition-all"
                                        style={{ width: `${Math.round((reset.processed / reset.total) * 100)}%` }}
                                    />
                                </div>
                            )}
                            {reset.failures && reset.failures.length > 0 && (
                                <ul className="text-destructive space-y-0.5 text-xs">
                                    {reset.failures.map((f, i) => (
                                        <li key={i}>
                                            {f.username ?? `#${f.id}`} ({f.site ?? 'site'}): {f.error ?? 'failed'}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {withPasswords.length > 0 && (
                        <Alert className="border-dashed border-amber-400/60 bg-amber-50 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            <AlertTitle className="flex items-center justify-between gap-2">
                                <span>New passwords — shown once</span>
                                <Button size="sm" variant="outline" onClick={copyAll} className="h-7">
                                    Copy all
                                </Button>
                            </AlertTitle>
                            <AlertDescription>
                                <p className="mb-2 text-xs">
                                    WordPress never returns a password after it's set. Copy and deliver these now — they won't show again.
                                </p>
                                <ul className="space-y-1">
                                    {withPasswords.map((r, i) => (
                                        <li key={i} className="flex flex-wrap items-center gap-2 font-mono text-xs">
                                            <span className="min-w-0 flex-1 truncate font-sans">
                                                {r.username ?? `#${r.id}`} · {r.site ?? 'unknown site'}
                                            </span>
                                            <span className="rounded bg-black/5 px-1.5 py-0.5 dark:bg-white/10">{r.password}</span>
                                            <button
                                                type="button"
                                                onClick={() => copy(r.password ?? '')}
                                                className="text-brand-600 dark:text-brand-400 font-sans hover:underline"
                                            >
                                                Copy
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}

                    {reset?.status === 'succeeded' && withPasswords.length === 0 && resultsExpired && (
                        <p className="text-muted-foreground text-xs">
                            This reset already ran and its passwords were shown and cleared (or the 30-minute window passed) — they can't be retrieved
                            again. Run it again if anyone still needs a new one.
                        </p>
                    )}
                </div>

                <DialogFooter className="shrink-0 border-t pt-4">
                    {withPasswords.length > 0 ? (
                        <Button onClick={done}>Done — I've saved these</Button>
                    ) : (
                        <Button
                            variant="destructive"
                            disabled={starting || busy}
                            onClick={() => {
                                if (
                                    confirm(
                                        "Reset the password for every synced WordPress user across every connected site? Everyone's current password stops working immediately.",
                                    )
                                )
                                    start();
                            }}
                        >
                            {busy ? 'Resetting…' : 'Reset ALL passwords'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
