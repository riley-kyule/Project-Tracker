import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

type Params = {
    client_id: string;
    redirect_uri: string;
    state?: string;
    scope?: string;
    code_challenge?: string;
    code_challenge_method?: string;
};

export default function McpOAuthAuthorize({ params, clientName }: { params: Params; clientName: string }) {
    const approveForm = useForm(params);
    const denyForm = useForm({ client_id: params.client_id, redirect_uri: params.redirect_uri, state: params.state ?? '' });

    const approve = (e: React.FormEvent) => {
        e.preventDefault();
        approveForm.post('/oauth/authorize');
    };

    const deny = (e: React.FormEvent) => {
        e.preventDefault();
        denyForm.post('/oauth/deny');
    };

    return (
        <AuthLayout title="Connect an AI assistant" description={`"${clientName}" is asking to connect to your EWMS account.`}>
            <Head title="Connect an AI assistant" />
            <div className="flex flex-col gap-4">
                <div className="border-sidebar-border/70 dark:border-sidebar-border flex items-start gap-3 rounded-xl border p-4">
                    <ShieldCheck className="text-brand-600 dark:text-brand-400 mt-0.5 size-5 shrink-0" />
                    <div className="text-sm">
                        <p className="font-medium">It will be able to:</p>
                        <ul className="text-muted-foreground mt-1 list-inside list-disc space-y-0.5">
                            <li>Read aggregate company numbers — headcount, task and ticket status, leave, payroll totals, traffic</li>
                            <li>Summarize and answer questions about that data</li>
                        </ul>
                        <p className="text-muted-foreground mt-2">It will never see an individual employee's salary or payslip.</p>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <form onSubmit={deny}>
                        <Button type="submit" variant="outline" disabled={denyForm.processing}>
                            Cancel
                        </Button>
                    </form>
                    <form onSubmit={approve}>
                        <Button type="submit" disabled={approveForm.processing}>
                            Allow
                        </Button>
                    </form>
                </div>
            </div>
        </AuthLayout>
    );
}
