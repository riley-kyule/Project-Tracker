import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Login from './login';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: usePageMock,
    Head: () => null,
}));

vi.mock('@/layouts/auth-layout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

function setPageProps(errors: Record<string, string> = {}) {
    usePageMock.mockReturnValue({ props: { errors } });
}

describe('Login', () => {
    it('shows nothing extra when there is no sign-in error', () => {
        setPageProps();
        render(<Login canGoogleSso />);
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('renders a failed Google sign-in as a visible alert', () => {
        setPageProps({ email: 'Sign-in is restricted to company Google accounts.' });
        render(<Login canGoogleSso />);
        expect(screen.getByRole('alert')).toHaveTextContent('Sign-in is restricted to company Google accounts.');
    });

    it('still renders the Google button alongside the error', () => {
        setPageProps({ email: 'Google sign-in failed. Please try again.' });
        render(<Login canGoogleSso />);
        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /continue with google/i })).toBeInTheDocument();
    });
});
