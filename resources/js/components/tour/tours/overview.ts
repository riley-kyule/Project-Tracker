import { type TourDefinition } from '../types';

export const overviewTours: TourDefinition[] = [
    {
        id: 'ceo-dashboard',
        section: 'Overview & reports',
        title: 'CEO dashboard',
        summary: 'The whole company on one page: what needs attention, departments, people, leave and web traffic.',
        href: '/dashboards/ceo',
        audience: (a) => a.isExec,
        steps: [
            {
                target: 'ceo-stats',
                title: 'What needs attention',
                body: "Overdue, blocked and awaiting-review tasks, open leave requests, critical tickets and this week's completions. Click any tile to see the list behind it.",
            },
            {
                target: 'ceo-quick-links',
                title: 'Jump to a section',
                body: 'The page is long; these chips scroll straight to the section you want.',
            },
            {
                target: 'ceo-department-performance',
                title: 'Department performance',
                body: "Each department's work side by side. Click a department's figures to open its tasks in Task Reports, or the icon to open that department's own dashboard.",
            },
            {
                target: 'ceo-employee-workload',
                title: 'Who is carrying what',
                body: 'Open tasks per person. Click a name to see their tasks in Task Reports. This is for spotting overload, not for ranking people.',
            },
            {
                target: 'ceo-ceo-priority',
                title: 'CEO priorities',
                body: 'Every task flagged as a CEO priority, wherever it lives. Upcoming deadlines for the next 7 days sit beside it.',
            },
            {
                target: 'ceo-leave-overview',
                title: 'Leave at a glance',
                body: 'Leave waiting for a decision, who is off in the next 30 days, and upcoming public holidays.',
            },
            {
                target: 'ceo-traffic-data',
                title: 'Traffic and marketing',
                body: 'GA4 traffic, Google Search Console results and a website comparison from the latest saved analytics. Open Marketing Statistics for the full picture.',
            },
            {
                target: 'ceo-wordpress-staff',
                title: 'WordPress staff access',
                body: 'Who has a login on each company website. Search by name or filter by site.',
            },
            {
                target: 'ceo-recent-activity',
                title: 'Recent activity',
                body: 'The latest important changes across the system, from the audit log.',
            },
        ],
    },
    {
        id: 'department-dashboard',
        section: 'Overview & reports',
        title: 'My Department',
        summary: "Your department's workload, gaps and deadlines, plus the SEO or CS score board if your team uses one.",
        href: '/dashboards/department',
        audience: (a) => a.managesDepartment || a.isExec,
        steps: [
            {
                target: 'dept-switch',
                title: 'Pick a department',
                body: 'You oversee more than one department: switch between them here.',
            },
            {
                target: 'dept-stats',
                title: 'Department health',
                body: 'Open, unassigned, overdue and blocked tasks, work awaiting review, and open support tickets for your department. Red tiles need action.',
            },
            {
                target: 'dept-workload',
                title: 'Workload by person',
                body: 'How many open and overdue tasks each person has. Click a column heading to sort. Use it to balance work, not to rank people.',
            },
            {
                target: 'dept-unassigned',
                title: 'Nobody owns these yet',
                body: 'Tasks in your department with no assignee. Open one and assign it so it does not fall through the cracks. Upcoming deadlines and recent completions are listed next to it.',
            },
        ],
    },
    {
        id: 'it-dashboard',
        section: 'Overview & reports',
        title: 'IT dashboard',
        summary: 'The live state of the support queue and how quickly tickets are handled.',
        href: '/dashboards/it',
        audience: (a) => a.has('tickets.manage'),
        steps: [
            {
                target: 'it-stats',
                title: 'The queue right now',
                body: 'New and unassigned tickets, critical ones, overdue ones and response-time breaches. Anything red is where to start.',
            },
            {
                target: 'it-averages',
                title: 'How fast we are',
                body: 'Average time to first response and to resolution over the last 30 days, and the share fixed remotely.',
            },
            {
                target: 'it-resolution',
                title: 'How tickets get fixed',
                body: 'Remote, in office or onsite, or by a third party, plus which categories have the most open tickets.',
            },
            {
                target: 'it-queue',
                title: 'Priority queue',
                body: 'The most urgent open tickets first. Click a number to open the ticket.',
            },
            {
                target: 'it-sla-link',
                title: 'Response targets',
                body: 'Configure SLAs sets how quickly each priority must be answered and resolved.',
            },
        ],
    },
    {
        id: 'task-reports',
        section: 'Overview & reports',
        title: 'Task Reports',
        summary: 'Filter every task you can see, save filters and reassign work in bulk.',
        href: '/reports/tasks',
        audience: (a) => a.has('reports.view'),
        steps: [
            {
                target: 'reports-filters',
                title: 'Slice the work',
                body: 'Show overdue, blocked, awaiting review or completed-this-week tasks, then narrow by department and assignee. Save filter keeps the combination as a one-click chip.',
            },
            {
                target: 'reports-table',
                title: 'The list',
                body: 'Sort by any heading. Tick tasks (or the header box for the whole page) to reassign them all to someone else at once.',
            },
            {
                target: 'reports-links',
                title: 'Other reports',
                body: 'Workload shows open, overdue and blocked counts per person. Remote support shows how support tickets were resolved.',
            },
        ],
    },
    {
        id: 'workload-report',
        section: 'Overview & reports',
        title: 'Workload report',
        summary: 'Open, overdue, blocked and awaiting-review counts for each person.',
        href: '/reports/workload',
        audience: (a) => a.has('reports.view'),
        steps: [
            {
                target: 'workload-table',
                title: 'Workload and exceptions',
                body: "Each person's open, overdue, blocked and awaiting-review tasks. Click a number to open exactly those tasks. Sort a column to find who needs help first.",
            },
        ],
    },
    {
        id: 'remote-support-report',
        section: 'Overview & reports',
        title: 'Remote support report',
        summary: 'How support tickets were resolved over a period, and how fast.',
        href: '/reports/remote-support',
        audience: (a) => a.has('reports.view'),
        steps: [
            {
                target: 'remote-filters',
                title: 'Choose a period',
                body: 'Pick a date range and, if you like, one department.',
            },
            {
                target: 'remote-stats',
                title: 'The results',
                body: 'Tickets resolved, average first response, resolution and hands-on time, and how often tickets were reopened. Below, the split between remote, office, onsite and third-party fixes.',
            },
        ],
    },
    {
        id: 'marketing',
        section: 'Overview & reports',
        title: 'Marketing Statistics',
        summary: 'Website traffic and search performance from GA4, Search Console and ad sources.',
        href: '/marketing-statistics',
        audience: (a) => a.canViewMarketingStatistics,
        steps: [
            {
                target: 'marketing-tabs',
                title: 'One tab per source',
                body: 'Overview sums everything up. GA4 is visitors and sessions, Google Search Console is search clicks and rankings, Website Comparison puts sites side by side, and Data Freshness shows when each source last updated.',
            },
            {
                target: 'marketing-filters',
                title: 'Choose sites and dates',
                body: 'Pick one website or all of them, a date range, and optionally a comparison period. Nothing changes until you press Apply; Reset goes back to the defaults.',
            },
            {
                target: 'marketing-refresh',
                title: 'Fresh numbers',
                body: "Data is saved once a day so pages stay fast. Refresh pulls today's figures live when you need them. A warning badge beside the title means a source could not update and you are seeing the last saved copy.",
            },
        ],
    },
    {
        id: 'my-reports',
        section: 'Overview & reports',
        title: 'My System Reports',
        summary: 'Reports for the websites you are assigned to.',
        href: '/my-reports',
        audience: (a) => a.hasWebsiteAssignments,
        steps: [
            {
                target: 'my-reports-sites',
                title: 'Your websites',
                body: 'Only the sites you are assigned to are listed. Tick the ones you want in the report.',
            },
            {
                target: 'my-reports-generate',
                title: 'Generate or export',
                body: 'Choose the date range, then Generate Report to view it here, or Export PDF to download a copy to share.',
            },
        ],
    },
];
