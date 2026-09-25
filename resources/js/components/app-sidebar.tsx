import { NavMain, type NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { UpdateChecker } from '@/components/update-checker';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Banknote,
    BarChart3,
    Boxes,
    Building2,
    CalendarDays,
    Crown,
    FileText,
    Gauge,
    Headset,
    IdCard,
    KanbanSquare,
    LayoutGrid,
    LifeBuoy,
    LineChart,
    ListTodo,
    Mail,
    Plug,
    Search,
    ShieldCheck,
    Sliders,
    Tag,
    Target,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    const has = (permission: string) => auth.permissions.includes(permission);
    const isExec = auth.roles.includes('CEO') || auth.roles.includes('Administrator');

    const work: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Boards', url: '/boards', icon: KanbanSquare },
        { title: 'Service Desk', url: '/tickets', icon: LifeBuoy },
        { title: 'Projects', url: '/projects', icon: ListTodo },
    ];

    const overview: NavItem[] = [
        ...(isExec ? [{ title: 'CEO Dashboard', url: '/dashboards/ceo', icon: Crown }] : []),
        ...(auth.managesDepartment ? [{ title: 'My Department', url: '/dashboards/department', icon: UsersRound }] : []),
        // CEO/Administrator don't necessarily "manage" any department themselves
        // (managesDepartment above is about being someone's manager/assistant),
        // so without this they'd have no discoverable way into the SEO Board at
        // all — a redirect straight to My Department scoped to SEO.
        ...(isExec && !auth.managesDepartment ? [{ title: 'SEO Board', url: '/seo-board/hod', icon: Search }] : []),
        ...(isExec && !auth.managesDepartment ? [{ title: 'CS Board', url: '/cs-board/hod', icon: Headset }] : []),
        ...(has('tickets.manage') ? [{ title: 'IT Dashboard', url: '/dashboards/it', icon: Gauge }] : []),
        ...(has('reports.view') ? [{ title: 'Task Reports', url: '/reports/tasks', icon: BarChart3 }] : []),
        ...(auth.canViewMarketingStatistics ? [{ title: 'Marketing Statistics', url: '/marketing-statistics', icon: LineChart }] : []),
    ];

    const hr: NavItem[] = [
        ...(has('hr.employees.view') ? [{ title: 'People', url: '/hr/employees', icon: Users }] : []),
        ...(has('hr.assets.view') ? [{ title: 'Assets', url: '/hr/assets', icon: Boxes }] : []),
        ...(has('hr.leave.view') ? [{ title: 'Leave', url: '/hr/leave', icon: CalendarDays }] : []),
        ...(has('hr.payroll.view') ? [{ title: 'Payroll', url: '/hr/payroll', icon: Banknote }] : []),
        ...(has('hr.performance.view') ? [{ title: 'Performance', url: '/hr/performance', icon: Target }] : []),
    ];

    const personal: NavItem[] = [
        ...(auth.hasEmployeeRecord ? [{ title: 'My Employee Data', url: '/hr/me/profile', icon: IdCard }] : []),
        ...(auth.hasEmployeeRecord ? [{ title: 'Leave Application', url: '/hr/me/leave', icon: CalendarDays }] : []),
        ...(auth.hasEmployeeRecord ? [{ title: 'My Payslips', url: '/hr/me/payslips', icon: Banknote }] : []),
        ...(auth.hasWebsiteAssignments ? [{ title: 'My System Reports', url: '/my-reports', icon: FileText }] : []),
        ...(auth.isSeoEmployee ? [{ title: 'My SEO Cards', url: '/seo-board', icon: Search }] : []),
        ...(auth.isCsEmployee ? [{ title: 'My CS Cards', url: '/cs-board', icon: Headset }] : []),
    ];

    const admin: NavItem[] = [
        ...(has('tickets.manage') ? [{ title: 'SLA Policies', url: '/admin/sla-policies', icon: Sliders }] : []),
        ...(has('wordpress.manage') ? [{ title: 'WordPress Users', url: '/admin/wordpress-users', icon: UserCog }] : []),
        ...(has('departments.view') ? [{ title: 'Departments', url: '/admin/departments', icon: Building2 }] : []),
        ...(has('labels.manage') ? [{ title: 'Labels', url: '/admin/labels', icon: Tag }] : []),
        ...(has('users.view') ? [{ title: 'Users', url: '/admin/users', icon: UserCog }] : []),
        ...(has('system.deploy') ? [{ title: 'Queue Health', url: '/admin/queue-health', icon: Activity }] : []),
        ...(has('system.deploy') ? [{ title: 'System Report Log', url: '/admin/report-deliveries', icon: Mail }] : []),
        ...(has('permissions.manage') ? [{ title: 'Permissions', url: '/admin/permissions', icon: ShieldCheck }] : []),
        ...(has('mcp.manage') ? [{ title: 'MCP Connector', url: '/admin/mcp', icon: Plug }] : []),
    ];

    const groups: NavGroup[] = [
        { label: 'Work', items: work },
        { label: 'Overview', items: overview },
        { label: 'HR', items: hr },
        { label: 'Personal', items: personal },
        { label: 'Admin', items: admin },
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
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                {has('system.deploy') && <UpdateChecker />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
