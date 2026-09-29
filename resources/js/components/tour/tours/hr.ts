import { type TourDefinition } from '../types';

export const hrTours: TourDefinition[] = [
    {
        id: 'people',
        section: 'HR',
        title: 'People',
        summary: 'The employee register: find anyone, add new staff and set staff numbering.',
        href: '/hr/employees',
        audience: (a) => a.has('hr.employees.view'),
        steps: [
            {
                target: 'people-filters',
                title: 'Find someone',
                body: 'Search by name, staff number or role, filter by employment status, and tick Include terminated to see people who have left.',
            },
            {
                target: 'people-table',
                title: 'The register',
                body: 'Everyone you are allowed to see, with department, role and status. "no login" means the record is not linked to a system account yet. Click a name to open the full record.',
            },
            {
                target: 'people-actions',
                title: 'Add staff',
                body: 'New employee creates a record (department is required; link a login if they will use EWMS). Numbering sets the staff number prefix, so new numbers are suggested for you.',
                when: (a) => a.has('hr.employees.manage'),
            },
        ],
    },
    {
        id: 'employee-record',
        section: 'HR',
        title: 'An employee record',
        summary: 'Everything about one person: profile, next of kin, contracts, documents, assets, pay items, performance and pay.',
        where: 'Open anyone from People.',
        audience: (a) => a.has('hr.employees.view'),
        steps: [
            {
                target: 'employee-summary',
                title: 'At a glance',
                body: 'Role, department, staff number, status and length of service.',
            },
            {
                target: 'employee-tabs',
                title: 'One tab per topic',
                body: 'Profile (personal, statutory, employment and bank details), Next of Kin, Contracts (add or renew), Documents (upload files), Assets in their care, recurring Pay items, and Performance goals and reviews.',
            },
            {
                target: 'employee-tabs',
                title: 'Compensation',
                body: 'The Compensation tab holds salary history. Only people with pay access can see it.',
                when: (a) => a.has('hr.compensation.view'),
            },
            {
                target: 'employee-edit',
                title: 'Keep it up to date',
                body: 'Edit the profile here. Every change is recorded in the audit log with who made it.',
                when: (a) => a.has('hr.employees.manage'),
            },
        ],
    },
    {
        id: 'assets',
        section: 'HR',
        title: 'Assets',
        summary: 'The company asset register: laptops, phones and equipment, and who has them.',
        href: '/hr/assets',
        audience: (a) => a.has('hr.assets.view'),
        steps: [
            {
                target: 'assets-filters',
                title: 'Find an asset',
                body: 'Search by tag, name, serial number or the person holding it, and filter by status.',
            },
            {
                target: 'assets-table',
                title: 'The register',
                body: 'Every asset with its tag, category, status, condition and current custodian. Click one to see its details and full history.',
            },
            {
                target: 'assets-actions',
                title: 'Add assets',
                body: 'New asset adds one item. Import CSV adds many at once from a spreadsheet. Categories keeps the list tidy (Laptops, Phones…).',
                when: (a) => a.has('hr.assets.manage'),
            },
        ],
    },
    {
        id: 'asset',
        section: 'HR',
        title: 'An asset',
        summary: 'Handing an asset out, taking it back and its history.',
        where: 'Open any asset from Assets.',
        audience: (a) => a.has('hr.assets.view'),
        steps: [
            {
                target: 'asset-actions',
                title: 'Hand out and take back',
                body: 'Assign gives it to an employee with the condition it left in and an expected return date. Record return takes it back, notes its condition and sets its new status. Edit and Delete are here too.',
                when: (a) => a.has('hr.assets.manage'),
            },
            {
                target: 'asset-history',
                title: 'Assignment history',
                body: 'Everyone who has had this asset, when, and the condition it went out and came back in.',
            },
        ],
    },
    {
        id: 'leave',
        section: 'HR',
        title: 'Leave',
        summary: 'Approving leave, the team calendar, and (for HR) balances, types, holidays and rules.',
        href: '/hr/leave',
        audience: (a) => a.has('hr.leave.view'),
        steps: [
            {
                target: 'leave-pending',
                title: 'Awaiting your decision',
                body: 'Requests from your team waiting on you. Approve, or Reject with a reason; the employee is notified either way. Click a name for the full request.',
            },
            {
                target: 'leave-calendar',
                title: 'Who is off when',
                body: 'The month at a glance: everyone on leave and public holidays. Use the arrows to change month; hover a name for the leave type and status.',
            },
            {
                target: 'leave-actions',
                title: 'Apply and manage',
                body: 'New application takes you to your own leave form. HR can also file on behalf of an employee, and manage Balances, Types, Holidays and Settings from here.',
            },
        ],
    },
    {
        id: 'leave-request',
        section: 'HR',
        title: 'A leave request',
        summary: 'The details, approval trail and decision for one request.',
        where: 'Open a request from Leave or from a notification.',
        audience: (a) => a.has('hr.leave.view'),
        steps: [
            {
                target: 'leave-request-details',
                title: 'The request',
                body: 'Dates, working days counted (weekends and public holidays excluded), reason, contact while away and who the work is handed over to.',
            },
            {
                target: 'leave-request-trail',
                title: 'Approval trail',
                body: 'Who decided and when, with any note they left.',
            },
            {
                target: 'leave-request-decision',
                title: 'Decide',
                body: 'Approve or reject with an optional note. If a colleague in the same department is already off, give an override reason to approve anyway. Approved days come off the balance automatically.',
            },
        ],
    },
    {
        id: 'leave-balances',
        section: 'HR',
        title: 'Leave balances',
        summary: "Every employee's entitlement, carried days, pending and available leave.",
        href: '/hr/leave/balances',
        audience: (a) => a.has('hr.leave.manage'),
        steps: [
            {
                target: 'balances-employee',
                title: 'Balances per person',
                body: 'Entitled, carried over, taken, pending, adjusted and available days for each leave type. Provision balances sets them up for someone new; Adjust changes a balance with a reason, which is logged.',
            },
        ],
    },
    {
        id: 'leave-types',
        section: 'HR',
        title: 'Leave types',
        summary: 'Annual, sick, maternity and your own custom types, with their rules.',
        href: '/hr/leave/types',
        audience: (a) => a.has('hr.leave.manage'),
        steps: [
            {
                target: 'leave-types-table',
                title: 'Types and their rules',
                body: 'Each type sets its default days, how it accrues, who is eligible, notice required, whether it needs approval or a document, and whether it counts toward the same-department block. Add a new type from the button above; switch one off instead of deleting it.',
            },
        ],
    },
    {
        id: 'leave-holidays',
        section: 'HR',
        title: 'Public holidays',
        summary: 'Holidays that are not counted as leave days.',
        href: '/hr/leave/holidays',
        audience: (a) => a.has('hr.leave.manage'),
        steps: [
            {
                target: 'holidays-add',
                title: 'Add a holiday',
                body: 'Enter the date and name. Tick recurring for holidays on the same date every year. Leave that spans a holiday does not use a day for it.',
            },
        ],
    },
    {
        id: 'leave-settings',
        section: 'HR',
        title: 'Leave rules',
        summary: 'Entitlement, accrual, carry-over, the same-department block and notice.',
        href: '/hr/leave/settings',
        audience: (a) => a.has('hr.leave.manage'),
        steps: [
            {
                target: 'leave-settings-entitlement',
                title: 'Entitlement',
                body: 'When the leave year starts, default annual days, whether leave builds up monthly or is granted up front, and how many unused days carry into next year.',
            },
            {
                target: 'leave-settings-overlap',
                title: 'Same-department block',
                body: 'Stop two people in one department being off at the same time. Choose which leave types are exempt and which roles may override the block.',
            },
            {
                target: 'leave-settings-requests',
                title: 'Requests',
                body: 'Minimum notice before leave starts, and whether every request must name a handover contact. Remember to Save settings.',
            },
        ],
    },
    {
        id: 'payroll',
        section: 'HR',
        title: 'Payroll',
        summary: 'Monthly payroll runs, statutory rates and payroll settings.',
        href: '/hr/payroll',
        audience: (a) => a.has('hr.payroll.view'),
        steps: [
            {
                target: 'payroll-periods',
                title: 'Payroll runs',
                body: "One row per month with its status and totals. Click a period to process, review and pay it. New period starts next month's run.",
            },
            {
                target: 'payroll-actions',
                title: 'Settings and rates',
                body: 'Settings holds employer details, the payslip letterhead and how payslips are sent. Statutory rates holds PAYE bands, NSSF, SHIF, Housing Levy and NITA rates.',
            },
        ],
    },
    {
        id: 'payroll-run',
        section: 'HR',
        title: 'Running payroll',
        summary: 'Process, check, approve and send payslips for one month.',
        where: 'Open a period from Payroll.',
        audience: (a) => a.has('hr.payroll.view'),
        steps: [
            {
                target: 'payroll-status',
                title: 'Where the run is',
                body: 'Draft → Review (processed, check the figures) → Approved → Paid. Payslips go out when the run is paid.',
            },
            {
                target: 'payroll-run-actions',
                title: 'Process, approve, send',
                body: 'Process calculates every payslip from salaries, pay items and statutory rates. Re-run if something changes. Approve and Send payslips emails each employee their PDF. If a second sign-off is required, a CEO or Administrator approves.',
            },
            {
                target: 'payroll-totals',
                title: 'Totals',
                body: 'Gross pay, PAYE, net pay and total employer cost for the month. Check these before approving.',
            },
            {
                target: 'payroll-exports',
                title: 'Statutory returns',
                body: 'Download CSVs for PAYE, NSSF, SHIF, Housing Levy, NITA, the bank payment schedule and the muster roll.',
            },
            {
                target: 'payroll-payslips',
                title: 'Payslips',
                body: "Each employee's payslip. Click one to see the full breakdown.",
            },
        ],
    },
    {
        id: 'payslip',
        section: 'HR',
        title: 'Reading a payslip',
        summary: 'Earnings, deductions, employer contributions and year to date.',
        where: 'Open a payslip from a payroll run.',
        audience: (a) => a.has('hr.payroll.view'),
        steps: [
            {
                target: 'payslip-earnings',
                title: 'Earnings',
                body: 'Basic pay plus allowances and other earnings for the month.',
            },
            {
                target: 'payslip-deductions',
                title: 'Deductions',
                body: 'PAYE, NSSF, SHIF, Housing Levy and any recurring deductions. Employer contributions and year-to-date totals follow below.',
            },
        ],
    },
    {
        id: 'payroll-settings',
        section: 'HR',
        title: 'Payroll settings',
        summary: 'Employer identifiers, payslip letterhead and sending rules.',
        href: '/hr/payroll/settings',
        audience: (a) => a.has('hr.payroll.process'),
        steps: [
            {
                target: 'payroll-settings-employer',
                title: 'Employer details',
                body: 'Company KRA PIN, NSSF and SHA/SHIF employer numbers, currency and the default pay day. These appear on payslips and statutory returns.',
            },
            {
                target: 'payroll-settings-letterhead',
                title: 'Letterhead',
                body: 'Upload the logo and set the company name and address printed at the top of every payslip.',
            },
            {
                target: 'payroll-settings-sending',
                title: 'Sending and approval',
                body: 'Send payslips as soon as the run is paid or on the pay date, and choose whether a CEO or Administrator must sign off first.',
            },
        ],
    },
    {
        id: 'rate-sets',
        section: 'HR',
        title: 'Statutory rates',
        summary: 'PAYE bands and statutory deduction rates used by payroll.',
        href: '/hr/payroll/rate-sets',
        audience: (a) => a.has('hr.payroll.process'),
        steps: [
            {
                target: 'rate-sets-list',
                title: 'Rate sets',
                body: 'Each set holds PAYE bands and the NSSF, SHIF, Housing Levy and NITA rates, with the dates it is effective from and to. When the law changes, add a new set rather than editing the old one, so past payrolls stay correct.',
            },
        ],
    },
    {
        id: 'performance',
        section: 'HR',
        title: 'Performance',
        summary: 'Review cycles and the reviews inside them.',
        href: '/hr/performance',
        audience: (a) => a.has('hr.performance.view'),
        steps: [
            {
                target: 'performance-cycles',
                title: 'Review cycles',
                body: 'Each cycle (for example "H1 2026") groups a round of reviews. Click one to open it. HR creates new cycles from the button above.',
            },
            {
                target: 'performance-reviews',
                title: 'Reviews in the cycle',
                body: 'Activate & open reviews creates a review for everyone. Each person writes a self assessment, their manager adds theirs and shares it, then the employee acknowledges it. Click a review to open it.',
            },
        ],
    },
    {
        id: 'performance-review',
        section: 'HR',
        title: 'A performance review',
        summary: 'Self assessment, manager assessment, sharing and acknowledging.',
        where: 'Open a review from Performance or from its notification.',
        steps: [
            {
                target: 'review-self',
                title: 'Self assessment',
                body: 'The employee writes a summary of their period. Save as you go, then Submit when ready; after that it is locked and goes to the manager.',
            },
            {
                target: 'review-manager',
                title: 'Manager assessment',
                body: 'The manager adds a summary, strengths, development areas and an overall rating (1 to 5), then Shares it with the employee, who reads it and presses Acknowledge.',
            },
        ],
    },
];

export const personalTours: TourDefinition[] = [
    {
        id: 'my-profile',
        section: 'Personal',
        title: 'My employee data',
        summary: 'Your HR record, contracts, assets in your care and documents.',
        href: '/hr/me/profile',
        audience: (a) => a.hasEmployeeRecord,
        steps: [
            {
                target: 'me-personal',
                title: 'Your record',
                body: 'Personal, statutory, employment and payment details, next of kin, contract history, the company assets you hold and your documents.',
            },
            {
                target: 'me-contact-hr',
                title: 'Something wrong?',
                body: 'This page is read-only. If anything is out of date, contact HR and they will update it.',
            },
        ],
    },
    {
        id: 'my-leave',
        section: 'Personal',
        title: 'Applying for leave',
        summary: 'Your balances, the leave form and your past requests.',
        href: '/hr/me/leave',
        audience: (a) => a.hasEmployeeRecord,
        steps: [
            {
                target: 'my-leave-balances',
                title: 'What you have left',
                body: 'Your available days for each leave type, after anything already taken or pending.',
            },
            {
                target: 'my-leave-apply',
                title: 'Apply',
                body: 'Pick the type and dates; weekends and public holidays are not counted. Add a reason and who to contact while you are away. If a colleague in your department is already off, you will be warned: choose other dates or tick emergency leave.',
            },
            {
                target: 'my-leave-requests',
                title: 'Track your requests',
                body: 'Each request shows its status and any note from your approver. Use Cancel on a request if your plans change.',
            },
        ],
    },
    {
        id: 'my-payslips',
        section: 'Personal',
        title: 'My payslips',
        summary: 'Download your payslips.',
        href: '/hr/me/payslips',
        audience: (a) => a.hasEmployeeRecord,
        steps: [
            {
                target: 'my-payslips',
                title: 'Your payslips',
                body: 'One row per paid month with pay date, gross pay, deductions and net pay. Download the PDF for your records or a bank. New payslips are also emailed to you.',
            },
        ],
    },
];
