import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Building2, Crown, Folder, Gauge, Globe, KanbanSquare, LayoutGrid, LifeBuoy, ListTodo, Users, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Boards',
        url: '/boards',
        icon: KanbanSquare,
    },
    {
        title: 'Service Desk',
        url: '/tickets',
        icon: LifeBuoy,
    },
    {
        title: 'Projects',
        url: '/projects',
        icon: ListTodo,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/riley-kyule/Project-Tracker',
        icon: Folder,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    const isExec = auth.roles.includes('CEO') || auth.roles.includes('Administrator');

    const adminNavItems: NavItem[] = [
        ...(isExec ? [{ title: 'CEO Dashboard', url: '/dashboards/ceo', icon: Crown }] : []),
        ...(auth.roles.includes('Department Manager') ? [{ title: 'My Department', url: '/dashboards/department', icon: UsersRound }] : []),
        ...(auth.permissions.includes('reports.view') ? [{ title: 'Reports', url: '/reports/tasks', icon: BarChart3 }] : []),
        ...(auth.permissions.includes('tickets.manage') ? [{ title: 'IT Dashboard', url: '/dashboards/it', icon: Gauge }] : []),
        ...(auth.permissions.includes('registry.manage') ? [{ title: 'Websites', url: '/admin/websites', icon: Globe }] : []),
        ...(auth.permissions.includes('departments.view') ? [{ title: 'Departments', url: '/admin/departments', icon: Building2 }] : []),
        ...(auth.permissions.includes('users.view') ? [{ title: 'Users', url: '/admin/users', icon: Users }] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={[...mainNavItems, ...adminNavItems]} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
