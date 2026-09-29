import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PageTour } from '@/components/tour/page-tour';
import { TourProvider } from '@/components/tour/tour-provider';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    useFlashToasts();

    return (
        <TourProvider>
            <PageTour id="welcome" />
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <div className="flex flex-1 flex-col">{children}</div>
                </AppContent>
            </AppShell>
        </TourProvider>
    );
}
