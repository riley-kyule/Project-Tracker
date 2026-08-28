import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
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
        <AuthLayout title={title} description={message}>
            <Head title={title} />
            <p className="text-brand-600 dark:text-brand-400 -mt-4 text-center text-sm font-semibold">Error {status}</p>
            <div className="flex justify-center gap-3">
                <Button variant="outline" onClick={() => window.history.back()}>
                    Go back
                </Button>
                <Button asChild>
                    <Link href="/dashboard">Go to dashboard</Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
