import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';

type GitStatus = {
    branch: string;
    current_sha: string;
    remote_sha: string;
    up_to_date: boolean;
    behind_by: number;
    commits: string[];
};

type Deployment = {
    id: number;
    status: 'pending' | 'running' | 'succeeded' | 'failed';
    commit_before: string | null;
    commit_after: string | null;
    output: string | null;
    started_at: string | null;
    finished_at: string | null;
};

const csrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const statusVariant: Record<Deployment['status'], 'secondary' | 'default' | 'destructive'> = {
    pending: 'secondary',
    running: 'default',
    succeeded: 'secondary',
    failed: 'destructive',
};

export function UpdateChecker() {
    const [open, setOpen] = useState(false);
    const [checking, setChecking] = useState(false);
    const [status, setStatus] = useState<GitStatus | null>(null);
    const [deployment, setDeployment] = useState<Deployment | null>(null);
    const [enabled, setEnabled] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [pollHandle, setPollHandle] = useState<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = () => {
        if (pollHandle) {
            clearInterval(pollHandle);
            setPollHandle(null);
        }
    };

    const pollDeployment = (id: number) => {
        stopPolling();

        const handle = setInterval(() => {
            fetch(`/admin/deployments/${id}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((payload: { deployment: Deployment }) => {
                    setDeployment(payload.deployment);

                    if (payload.deployment.status === 'succeeded' || payload.deployment.status === 'failed') {
                        clearInterval(handle);
                        setPollHandle(null);
                    }
                })
                .catch(() => undefined);
        }, 3000);

        setPollHandle(handle);
    };

    const loadLatest = () => {
        fetch('/admin/deployments/latest', { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload: { deployment: Deployment | null; enabled: boolean }) => {
                setDeployment(payload.deployment);
                setEnabled(payload.enabled);

                if (payload.deployment && ['pending', 'running'].includes(payload.deployment.status)) {
                    pollDeployment(payload.deployment.id);
                }
            })
            .catch(() => undefined);
    };

    const checkForUpdates = () => {
        setChecking(true);
        setError(null);

        fetch('/admin/deployments/check', { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject()))
            .then((payload: GitStatus) => setStatus(payload))
            .catch(() => setError('Could not check for updates.'))
            .finally(() => setChecking(false));
    };

    const deployNow = () => {
        setError(null);

        fetch('/admin/deployments', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        })
            .then(async (response) => {
                const payload = await response.json();

                if (!response.ok) {
                    setError(payload.message ?? 'Could not start deployment.');
                    return;
                }

                setDeployment(payload.deployment);
                pollDeployment(payload.deployment.id);
            })
            .catch(() => setError('Could not start deployment.'));
    };

    const busy = deployment ? ['pending', 'running'].includes(deployment.status) : false;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    loadLatest();
                    checkForUpdates();
                } else {
                    stopPolling();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="w-full justify-start gap-2">
                    <RefreshCw className="size-4" />
                    Check for Updates
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle>Check for Updates</DialogTitle>
                    <DialogDescription>Fetches the latest commits on the deploy branch and, if enabled, runs the release sequence.</DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {!enabled && (
                        <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                            Self-deploy is disabled on this environment. Updates must be released through the normal deployment pipeline.
                        </p>
                    )}

                    {error && <p className="text-destructive text-sm">{error}</p>}

                    <div className="space-y-2 text-sm">
                        {checking && <p className="text-muted-foreground">Checking for updates…</p>}
                        {!checking && status && (
                            <>
                                <p>
                                    Branch <span className="font-mono">{status.branch}</span>:{' '}
                                    {status.up_to_date ? (
                                        <Badge variant="secondary">Up to date</Badge>
                                    ) : (
                                        <Badge>{status.behind_by} commit(s) behind</Badge>
                                    )}
                                </p>
                                {!status.up_to_date && status.commits.length > 0 && (
                                    <ul className="bg-muted/50 max-h-40 space-y-1 overflow-y-auto rounded-md p-2 font-mono text-xs">
                                        {status.commits.map((commit) => (
                                            <li key={commit}>{commit}</li>
                                        ))}
                                    </ul>
                                )}
                            </>
                        )}
                    </div>

                    {deployment && (
                        <div className="space-y-2 text-sm">
                            <p className="flex items-center gap-2">
                                Last deployment: <Badge variant={statusVariant[deployment.status]}>{deployment.status}</Badge>
                            </p>
                            {deployment.output && (
                                <pre className="bg-muted max-h-56 overflow-y-auto rounded-md p-2 font-mono text-xs whitespace-pre-wrap">
                                    {deployment.output}
                                </pre>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={checkForUpdates} disabled={checking}>
                        Check again
                    </Button>
                    <Button onClick={deployNow} disabled={!enabled || busy || !status || status.up_to_date}>
                        {busy ? 'Deploying…' : 'Deploy now'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
