import { type TourAudience, type TourDefinition } from '../types';

const hasAnyHr = (a: TourAudience) =>
    ['hr.employees.view', 'hr.assets.view', 'hr.leave.view', 'hr.payroll.view', 'hr.performance.view'].some((permission) => a.has(permission));

export const gettingStartedTours: TourDefinition[] = [
    {
        id: 'welcome',
        section: 'Getting started',
        title: 'Welcome to EWMS',
        summary: 'A one-minute look at the menu, search, notifications and where to get help.',
        href: '/dashboard',
        steps: [
            {
                title: 'Welcome to EWMS',
                body: "This is where the company's work lives: tasks and boards, IT and R&D support tickets, HR, and reports. This quick tour shows you around. Every page also has its own short tour the first time you open it.",
            },
            {
                target: 'sidebar-trigger',
                title: 'The menu',
                body: 'This button opens and collapses the menu. On a phone, tap it to see every page you have access to.',
            },
            {
                target: 'nav-work',
                title: 'Your daily work',
                body: 'Dashboard is your home page. Boards hold tasks as cards. Service Desk is where you ask IT or R&D for help. Projects group related tasks together.',
            },
            {
                target: 'nav-overview',
                title: 'Overviews and reports',
                body: 'Dashboards and reports for the areas you oversee. You only see the ones your role allows.',
                when: (a) => a.isExec || a.managesDepartment || a.has('tickets.manage') || a.has('reports.view') || a.canViewMarketingStatistics,
            },
            {
                target: 'nav-hr',
                title: 'HR tools',
                body: 'The HR areas you look after: people, assets, leave, payroll and performance.',
                when: hasAnyHr,
            },
            {
                target: 'nav-personal',
                title: 'Your own records',
                body: 'Your employee details, leave applications, payslips and, if your team uses them, your daily score cards and system reports.',
                when: (a) => a.hasEmployeeRecord || a.hasWebsiteAssignments || a.isSeoEmployee || a.isCsEmployee,
            },
            {
                target: 'nav-admin',
                title: 'Admin tools',
                body: 'Company setup: users and roles, departments, labels, SLA policies and system health. Changes here affect everyone and are recorded in the audit log.',
                when: (a) => a.has('users.view') || a.has('labels.manage') || a.has('tickets.manage'),
            },
            {
                target: 'nav-admin',
                title: 'Departments',
                body: "Departments lists the company's departments, who leads each one and who belongs to them.",
                when: (a) => a.has('departments.view') && !(a.has('users.view') || a.has('labels.manage') || a.has('tickets.manage')),
            },
            {
                target: 'global-search',
                title: 'Search everything',
                body: 'Find tasks, tickets, boards and people from any page. Type a number like T-42 for a task or TK-7 for a ticket to find it instantly.',
            },
            {
                target: 'notifications',
                title: 'Notifications',
                body: 'The bell shows new assignments, mentions, approvals and ticket replies. The red number is how many are unread. Click one to open it, or use Mark all read.',
            },
            {
                target: 'user-menu',
                title: 'Your account',
                body: 'Click your name for Settings (profile, dark mode, which notifications you get) and to log out.',
            },
            {
                target: 'help-button',
                title: 'Help is always here',
                body: "Click ? on any page to replay that page's tour, or open the Help Center to read every guide for your role. That's it, you're ready.",
            },
        ],
    },
];
