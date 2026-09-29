import { type TourDefinition } from '../types';

export const adminTours: TourDefinition[] = [
    {
        id: 'admin-users',
        section: 'Admin',
        title: 'Users and roles',
        summary: 'Adding people, giving them a role and department, and deactivating accounts.',
        href: '/admin/users',
        audience: (a) => a.has('users.view'),
        steps: [
            {
                target: 'users-new',
                title: 'Add a user',
                body: 'Enter their name, company email, role, department and job title. They sign in with their company Google account and get a welcome email.',
                when: (a) => a.has('users.manage'),
            },
            {
                target: 'users-table',
                title: 'Everyone with an account',
                body: "Each person's role, department and status. The pencil changes their role, department, manager, job title or status: set Inactive or Suspended to block sign-in without losing their history. The role decides what they can see and do everywhere.",
            },
        ],
    },
    {
        id: 'admin-departments',
        section: 'Admin',
        title: 'Departments',
        summary: 'The company structure, department heads, board templates and summary emails.',
        href: '/admin/departments',
        audience: (a) => a.has('departments.view'),
        steps: [
            {
                target: 'departments-table',
                title: 'The structure',
                body: 'Every department and team, its head and assistant, and its members. Use the people icon to add extra members from other departments who should see its work.',
            },
            {
                target: 'departments-new',
                title: 'Add or edit a department',
                body: 'Set its parent (for teams inside a department), head and assistant manager, the column template its new boards start with, and when its daily and weekly summary emails go out.',
                when: (a) => a.has('departments.manage'),
            },
            {
                target: 'departments-ceo-summary',
                title: 'CEO summary emails',
                body: "When the CEO's daily and Friday weekly company summaries are sent.",
                when: (a) => a.has('departments.manage'),
            },
        ],
    },
    {
        id: 'admin-labels',
        section: 'Admin',
        title: 'Labels',
        summary: 'The coloured tags people put on tasks.',
        href: '/admin/labels',
        audience: (a) => a.has('labels.manage'),
        steps: [
            {
                target: 'labels-new',
                title: 'Create labels',
                body: 'Give a label a short name and a colour. It becomes available on every task.',
            },
            {
                target: 'labels-table',
                title: 'Keep them tidy',
                body: 'Rename or recolour a label and every task using it updates. Delete labels nobody needs so the list stays short.',
            },
        ],
    },
    {
        id: 'admin-sla',
        section: 'Admin',
        title: 'SLA policies',
        summary: 'How fast support tickets must be answered and resolved.',
        href: '/admin/sla-policies',
        audience: (a) => a.has('tickets.manage'),
        steps: [
            {
                target: 'sla-hours',
                title: 'Business hours',
                body: 'SLA clocks only run during these hours and working days, so a ticket raised on Friday evening is not late by Saturday.',
            },
            {
                target: 'sla-policy',
                title: 'Targets per priority',
                body: 'For each priority, the minutes allowed until the first response and until resolution. Response gap is how long a ticket may wait on the requester before it closes automatically. Leave blank for no limit.',
            },
        ],
    },
    {
        id: 'admin-permissions',
        section: 'Admin',
        title: 'Permissions',
        summary: 'What each role is allowed to do.',
        href: '/admin/permissions',
        audience: (a) => a.has('permissions.manage'),
        steps: [
            {
                target: 'permissions-roles',
                title: 'Pick a role',
                body: 'Each chip is a role with the number of people holding it. A lock means that role is fixed and cannot be edited.',
            },
            {
                target: 'permissions-role',
                title: 'Who has it',
                body: 'The people who currently hold this role.',
            },
            {
                target: 'permissions-search',
                title: 'Turn abilities on or off',
                body: 'Search for an ability, then flip its switch. Changes apply immediately to everyone with the role, and each one is recorded in the audit log.',
            },
        ],
    },
    {
        id: 'admin-mcp',
        section: 'Admin',
        title: 'MCP Connector',
        summary: 'Connecting Claude or ChatGPT to EWMS.',
        href: '/admin/mcp',
        audience: (a) => a.has('mcp.manage'),
        steps: [
            {
                target: 'mcp-oauth',
                title: 'Connect with OAuth',
                body: 'The easy way for Claude or ChatGPT: register the connector here and sign in when the AI app asks.',
            },
            {
                target: 'mcp-token-setup',
                title: 'Or use a token',
                body: 'For tools that need a plain token: create one, copy it straight away (it is shown only once) and paste it into the tool.',
            },
            {
                target: 'mcp-tokens',
                title: 'Tokens',
                body: "Every token issued, when it was last used, and a button to revoke it. Tokens also stop working while their owner's account is inactive or suspended, and every call an assistant makes is recorded in the audit log.",
            },
        ],
    },
    {
        id: 'admin-wordpress',
        section: 'Admin',
        title: 'WordPress Users',
        summary: 'Connecting company websites and managing their staff logins in one place.',
        href: '/admin/wordpress-users',
        audience: (a) => a.has('wordpress.manage'),
        steps: [
            {
                target: 'wp-connect',
                title: 'Connect a website',
                body: "Enter the site address plus a WordPress username and application password. EWMS then reads and manages that site's users for you.",
            },
            {
                target: 'wp-sites-table',
                title: 'Connected sites',
                body: 'Each site with its connection status, when it last synced and any error. Edit a site to update its credentials.',
            },
            {
                target: 'wp-user-actions',
                title: 'Manage staff logins',
                body: 'Sync all sites pulls the latest users. Add a user to one or more sites at once. Reset every staff password in one go when someone leaves; the new passwords are shown once, so save them.',
            },
            {
                target: 'wp-users-table',
                title: 'Everyone, across every site',
                body: 'Filter by site or role, or search. Tick users to change their role, update emails, reset passwords or delete them in bulk.',
            },
        ],
    },
    {
        id: 'admin-queue',
        section: 'Admin',
        title: 'Queue Health',
        summary: 'Background jobs: emails, reports, syncs.',
        href: '/admin/queue-health',
        audience: (a) => a.has('system.deploy'),
        steps: [
            {
                target: 'queue-pending',
                title: 'Waiting jobs',
                body: 'Emails, reports and syncs waiting to run, per queue. A number that keeps growing means the background worker is not running.',
            },
            {
                target: 'queue-failed',
                title: 'Failed jobs',
                body: 'Jobs that failed, with the error. A few are normal (for example a website that was briefly down); many of the same error need a developer.',
            },
        ],
    },
    {
        id: 'admin-report-log',
        section: 'Admin',
        title: 'System Report Log',
        summary: 'Whether scheduled report emails were delivered.',
        href: '/admin/report-deliveries',
        audience: (a) => a.has('system.deploy'),
        steps: [
            {
                target: 'report-log-table',
                title: 'Every report email',
                body: 'Each scheduled report, when it went out, who received it and, if it failed, why.',
            },
            {
                target: 'report-log-filter',
                title: 'Find failures',
                body: 'Filter by status to see only failed deliveries.',
            },
        ],
    },
];

export const settingsTours: TourDefinition[] = [
    {
        id: 'settings',
        section: 'Settings',
        title: 'Your settings',
        summary: 'Profile, appearance and which notifications you receive.',
        href: '/settings/profile',
        steps: [
            {
                target: 'settings-nav-profile',
                title: 'Profile',
                body: 'Your name and email address.',
            },
            {
                target: 'settings-nav-appearance',
                title: 'Appearance',
                body: 'Light mode, dark mode, or follow your device.',
            },
            {
                target: 'settings-nav-notifications',
                title: 'Notifications',
                body: 'Choose which updates you receive about tasks, the service desk, analytics and reports. Fewer notifications, less noise.',
            },
            {
                target: 'settings-nav-integrations',
                title: 'Integrations',
                body: 'Company-wide email delivery, browser push and backups. Only the CEO and Administrators see this.',
            },
        ],
    },
    {
        id: 'settings-integrations',
        section: 'Settings',
        title: 'Integrations',
        summary: 'Backups, email delivery and browser push for the whole company.',
        href: '/settings/integrations',
        audience: (a) => a.isExec,
        steps: [
            {
                target: 'integrations-backups',
                title: 'Backups',
                body: 'Connect Google Drive, then choose how often backups run, at what time, and how many to keep.',
            },
            {
                target: 'integrations-email',
                title: 'Email',
                body: 'The mail server EWMS sends from. Leave it on Log while testing so nothing is actually sent. Subjects are set automatically per email.',
            },
            {
                target: 'integrations-push',
                title: 'Browser push',
                body: 'Connection details for browser push notifications. Leave blank to turn push off.',
            },
        ],
    },
];
