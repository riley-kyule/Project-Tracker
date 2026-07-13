import { SidebarProvider } from '@/components/ui/sidebar';
import { useState } from 'react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const [isOpen, setIsOpen] = useState(() => (typeof window !== 'undefined' ? localStorage.getItem('sidebar') !== 'false' : true));

    const handleSidebarChange = (open: boolean) => {
        setIsOpen(open);

        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar', String(open));
        }
    };

    const skipLink = (
        <a
            href="#main-content"
            className="bg-background text-foreground focus:ring-ring fixed top-2 left-2 z-50 -translate-y-20 rounded-md px-3 py-2 text-sm font-medium shadow focus:translate-y-0 focus:ring-2"
        >
            Skip to main content
        </a>
    );

    if (variant === 'header') {
        return (
            <>
                {skipLink}
                <div className="flex min-h-screen w-full flex-col">{children}</div>
            </>
        );
    }

    return (
        <>
            {skipLink}
            <SidebarProvider defaultOpen={isOpen} open={isOpen} onOpenChange={handleSidebarChange}>
                {children}
            </SidebarProvider>
        </>
    );
}
