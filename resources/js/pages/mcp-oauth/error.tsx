import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link } from '@inertiajs/react';

export default function McpOAuthError({ message }: { message: string }) {
    return (
        <AuthLayout title="Can't connect" description={message}>
            <Head title="Can't connect" />
            <div className="flex justify-center">
                <Button asChild>
                    <Link href="/dashboard">Go to dashboard</Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
