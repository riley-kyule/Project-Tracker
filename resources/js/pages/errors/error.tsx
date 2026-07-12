import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

const copy: Record<number, { title: string; message: string }> = {
    403: { title: 'Access denied', message: "You don't have permission to view this page. If this seems wrong, ask an administrator." },
    404: { title: 'Page not found', message: "The page you're looking for doesn't exist, or may have been moved or archived." },
    419: { title: 'Session expired', message: 'Your session timed out for security. Please refresh and sign in again.' },
    429: { title: 'Too many requests', message: "You've made too many requests in a short time. Please wait a moment and try again." },
    500: { title: 'Something went wrong', message: 'An unexpected error occurred on our end. Try again, and let IT know if it keeps happening.' },
    503: { title: 'Down for maintenance', message: "EWMS is briefly unavailable while we make updates. We'll be back shortly." },
};

export default function ErrorPage({ status }: { status: number }) {
    const { title, message } = copy[status] ?? copy[500];

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 text-center">
            <Head title={title} />
            <div className="bg-brand-600 flex size-14 items-center justify-center rounded-xl text-white">
                <AppLogoIcon className="size-8 fill-current" />
            </div>
            <div className="space-y-2">
                <p className="text-brand-600 dark:text-brand-400 text-sm font-semibold">Error {status}</p>
                <h1 className="text-foreground text-2xl font-semibold">{title}</h1>
                <p className="text-muted-foreground max-w-sm text-sm">{message}</p>
            </div>
            <div className="flex gap-3">
                <Button variant="outline" onClick={() => window.history.back()}>
                    Go back
                </Button>
                <Button asChild>
                    <Link href="/dashboard">Go to dashboard</Link>
                </Button>
            </div>
        </div>
    );
}
