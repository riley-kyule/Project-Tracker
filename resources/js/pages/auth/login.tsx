import { Head } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

function GoogleIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" aria-hidden="true">
            <path
                fill="#4285F4"
                d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.78-2.41 3.63v3.02h3.9c2.28-2.1 3.6-5.2 3.6-8.84z"
            />
            <path
                fill="#34A853"
                d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.9-3.02c-1.08.73-2.46 1.16-4.05 1.16-3.12 0-5.76-2.1-6.7-4.93H1.27v3.1C3.25 21.3 7.28 24 12 24z"
            />
            <path
                fill="#FBBC05"
                d="M5.3 14.31A7.2 7.2 0 0 1 4.9 12c0-.8.14-1.58.4-2.31v-3.1H1.27A11.98 11.98 0 0 0 0 12c0 1.93.46 3.76 1.27 5.41l4.03-3.1z"
            />
            <path
                fill="#EA4335"
                d="M12 4.77c1.76 0 3.34.6 4.58 1.79l3.44-3.44C17.95 1.19 15.24 0 12 0 7.28 0 3.25 2.7 1.27 6.59l4.03 3.1c.94-2.83 3.58-4.92 6.7-4.92z"
            />
        </svg>
    );
}

interface LoginProps {
    status?: string;
    canGoogleSso: boolean;
}

export default function Login({ status, canGoogleSso }: LoginProps) {
    return (
        <AuthLayout title="Log in to your account" description="Sign in with your company Google account">
            <Head title="Log in" />

            {canGoogleSso ? (
                <Button variant="outline" className="w-full" asChild>
                    <a href="/auth/google/redirect">
                        <GoogleIcon className="size-4" />
                        Continue with Google
                    </a>
                </Button>
            ) : (
                <p className="text-muted-foreground text-center text-sm">
                    Google sign-in isn't configured for this environment. Contact an administrator.
                </p>
            )}

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
