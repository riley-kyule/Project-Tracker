import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { UpdateChecker } from '@/components/update-checker';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Banknote,
    BarChart3,
    Building2,
    CalendarDays,
    Crown,
    FileText,
    Gauge,
    IdCard,
    KanbanSquare,
    Laptop,
    LayoutGrid,
    LifeBuoy,
    LineChart,
    ListTodo,
    Mail,
    Sliders,
    Tag,
    Target,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';
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

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    const isExec = auth.roles.includes('CEO') || auth.roles.includes('Administrator');

    const adminNavItems: NavItem[] = [
        ...(isExec ? [{ title: 'CEO Dashboard', url: '/dashboards/ceo', icon: Crown }] : []),
        ...(auth.managesDepartment ? [{ title: 'My Department', url: '/dashboards/department', icon: UsersRound }] : []),
        ...(auth.permissions.includes('reports.view') ? [{ title: 'Reports', url: '/reports/tasks', icon: BarChart3 }] : []),
        ...(auth.canViewMarketingStatistics ? [{ title: 'Marketing Statistics', url: '/marketing-statistics', icon: LineChart }] : []),
        ...(auth.permissions.includes('tickets.manage') ? [{ title: 'IT Dashboard', url: '/dashboards/it', icon: Gauge }] : []),
        ...(auth.permissions.includes('tickets.manage') ? [{ title: 'SLA Policies', url: '/admin/sla-policies', icon: Sliders }] : []),
        ...(auth.permissions.includes('wordpress.manage') ? [{ title: 'WordPress Users', url: '/admin/wordpress-users', icon: UserCog }] : []),
        ...(auth.permissions.includes('hr.employees.view') ? [{ title: 'People', url: '/hr/employees', icon: IdCard }] : []),
        ...(auth.permissions.includes('hr.assets.view') ? [{ title: 'Assets', url: '/hr/assets', icon: Laptop }] : []),
        ...(auth.permissions.includes('hr.leave.view') ? [{ title: 'Leave', url: '/hr/leave', icon: CalendarDays }] : []),
        ...(auth.permissions.includes('hr.payroll.view') ? [{ title: 'Payroll', url: '/hr/payroll', icon: Banknote }] : []),
        ...(auth.permissions.includes('hr.performance.view') ? [{ title: 'Performance', url: '/hr/performance', icon: Target }] : []),
        ...(auth.hasEmployeeRecord ? [{ title: 'My HR', url: '/hr/me/profile', icon: IdCard }] : []),
        ...(auth.hasEmployeeRecord ? [{ title: 'My Leave', url: '/hr/me/leave', icon: CalendarDays }] : []),
        ...(auth.hasEmployeeRecord ? [{ title: 'My Payslips', url: '/hr/me/payslips', icon: Banknote }] : []),
        ...(auth.permissions.includes('departments.view') ? [{ title: 'Departments', url: '/admin/departments', icon: Building2 }] : []),
        ...(auth.permissions.includes('labels.manage') ? [{ title: 'Labels', url: '/admin/labels', icon: Tag }] : []),
        ...(auth.permissions.includes('users.view') ? [{ title: 'Users', url: '/admin/users', icon: Users }] : []),
        ...(auth.hasWebsiteAssignments ? [{ title: 'My Reports', url: '/my-reports', icon: FileText }] : []),
        ...(auth.permissions.includes('system.deploy') ? [{ title: 'Queue Health', url: '/admin/queue-health', icon: Activity }] : []),
        ...(auth.permissions.includes('system.deploy') ? [{ title: 'Report Deliveries', url: '/admin/report-deliveries', icon: Mail }] : []),
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
                {auth.permissions.includes('system.deploy') && <UpdateChecker />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
