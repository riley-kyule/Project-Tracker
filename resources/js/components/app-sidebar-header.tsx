import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

function GlobalSearch() {
    const [term, setTerm] = useState('');

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (term.trim().length >= 2) {
                    router.get('/search', { q: term.trim() });
                    setTerm('');
                }
            }}
            className="relative hidden sm:block"
        >
            <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
            <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Search…" className="h-8 w-44 pl-8 lg:w-64" />
        </form>
    );
}

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex w-full items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
                <div className="ml-auto flex items-center gap-2">
                    <GlobalSearch />
                    <NotificationBell />
                </div>
            </div>
        </header>
    );
}
