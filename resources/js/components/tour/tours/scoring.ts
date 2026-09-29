import { type TourAudience, type TourDefinition } from '../types';

const leadsCs = (a: TourAudience) => a.has('cs.cards.approve') && (a.managesDepartment || a.isExec);
const leadsSeo = (a: TourAudience) => a.has('seo.cards.approve') && (a.managesDepartment || a.isExec);

export const scoringTours: TourDefinition[] = [
    // --- Customer Service ------------------------------------------------
    {
        id: 'cs-employee',
        section: 'Scoring boards',
        title: 'My Customer Service cards',
        summary: 'Your daily and weekly score cards, service log, sales, continuity checks and history.',
        where: 'Open your Customer Service board and switch to Score Based (or My CS Cards in the menu).',
        audience: (a) => a.isCsEmployee,
        steps: [
            {
                target: 'cs-emp-tabs',
                title: 'Six tabs',
                body: 'Today is your daily card, This Week your weekly plan and targets, Service your enquiry log, Sales your contact attempts and sales, Continuity your daily platform checks, and History your approved scores.',
            },
            {
                target: 'cs-emp-points',
                reveal: 'cs-emp-tab-today',
                title: 'Your points',
                body: 'Planned is what the card is worth. Submitted is what you have marked done. Approved only appears after your HOD reviews each item: submitting is not scoring.',
            },
            {
                target: 'cs-emp-status',
                reveal: 'cs-emp-tab-today',
                title: 'Update each item',
                body: 'Set the status as you work (in progress, submitted, or blocked with a reason), enter the quantity achieved where there is a target, add a comment and press Save.',
            },
            {
                target: 'cs-emp-evidence',
                reveal: 'cs-emp-tab-today',
                title: 'Attach evidence',
                body: 'Choose a file and press Attach evidence. Items marked "evidence required" need proof before your HOD can approve them.',
            },
            {
                target: 'cs-emp-commercial',
                reveal: 'cs-emp-tab-week',
                title: "This week's targets",
                body: 'Your commercial targets for the week (new customers, renewals and so on) and how far you are, counted from sales your HOD has cleared.',
            },
            {
                target: 'cs-emp-service-log',
                reveal: 'cs-emp-tab-service',
                title: 'Log every enquiry',
                body: 'Log each enquiry as it arrives, mark when you first respond, then set how it was resolved. Response times and resolutions feed your weekly service-quality score automatically.',
            },
            {
                target: 'cs-emp-activity',
                reveal: 'cs-emp-tab-sales',
                title: 'Log contact attempts',
                body: 'Record every follow-up, renewal or reactivation attempt, and update its stage as the conversation moves on. Attempts are evidence of work even when they do not lead to a sale.',
            },
            {
                target: 'cs-emp-sale',
                reveal: 'cs-emp-tab-sales',
                title: 'Record a sale',
                body: 'Enter the category (new, renewal, reactivation…), customer, payment reference and amount. It only counts toward your target once your HOD clears it. If a colleague helped close it, tell your HOD so they can split the credit.',
            },
            {
                target: 'cs-emp-continuity-checks',
                reveal: 'cs-emp-tab-continuity',
                title: 'Daily continuity checks',
                body: 'Once a day, run the five checks on your assigned platform and record the result of each.',
            },
            {
                target: 'cs-emp-report-issue',
                reveal: 'cs-emp-tab-continuity',
                title: 'Report a fault',
                body: 'Found a problem? Report it with an owner and severity. It stays open until someone confirms it is fixed, accepts it as a known exception, or reassigns it.',
            },
            {
                target: 'cs-emp-tab-history',
                title: 'Your history',
                body: 'Approved daily and weekly scores, commercial achievement by week and your final weekly score (70% daily plus 30% weekly).',
            },
        ],
    },
    {
        id: 'cs-hod',
        section: 'Scoring boards',
        title: 'Running the Customer Service board',
        summary: "For the HOD: approve cards, plan weeks, clear sales, log quality, assign platforms and follow the team's trends.",
        where: 'My Department, or the Customer Service board → Score Based.',
        audience: leadsCs,
        steps: [
            {
                target: 'cs-hod-tab-today',
                title: 'Today: decide each item',
                body: 'Expand a person to see their card. For every submitted item choose a decision: Approved (100%), Approved late (80%), Minor correction (75%), Major rework (50%), Rejected (0%), or Exempted / Excluded / Carried forward. Items left pending from earlier days appear on top so nothing is lost; Reopen reopens a closed card.',
            },
            {
                target: 'cs-hod-tab-week',
                title: 'This Week: plan and targets',
                body: "Set each person's commercial targets, build their weekly plan from the templates, then approve the plan. Until it is approved, weekly items cannot be decided.",
            },
            {
                target: 'cs-hod-tab-sales',
                title: 'Sales: clear or flag',
                body: 'Check the payment reference and evidence on each logged sale, then clear it so it counts (splitting the credit if it was shared with a colleague), or flag it (for example reversed) with a reason. The number shows how many are waiting.',
            },
            {
                target: 'cs-hod-tab-quality',
                title: 'Quality',
                body: 'Log customer complaints against a person and record sampled contact-quality reviews. Together with response times these make up the weekly service-quality item.',
            },
            {
                target: 'cs-hod-tab-assignments',
                title: 'Assignments',
                body: 'Assign each person the country or platform they look after, from a start date, with an optional backup colleague. Past assignments are kept so old cards can still be checked.',
            },
            {
                target: 'cs-hod-tab-continuity',
                title: 'Continuity issues',
                body: 'Faults reported by the team stay open until you close them as Resolved, Known exception or Reassigned.',
            },
            {
                target: 'cs-hod-tab-exceptions',
                title: 'Exceptions',
                body: 'Everyone with incomplete, late, rejected or blocked items, in one list, so you do not have to open every card.',
            },
            {
                target: 'cs-hod-tab-trends',
                title: 'Trends and history',
                body: "Trends compares the team's final weekly scores over recent weeks. History shows any one person's full record over a chosen period. Use them to support people, not to rank them.",
            },
            {
                target: 'cs-hod-settings',
                title: 'Settings',
                body: 'The task template library, who receives the nightly report, response-time standards, reporting currency and the calibration period.',
            },
        ],
    },
    {
        id: 'cs-settings',
        section: 'Scoring boards',
        title: 'Customer Service board settings',
        summary: 'Templates, report recipients and board rules.',
        href: '/cs-board/settings',
        audience: (a) => a.has('cs.templates.manage') || a.has('cs.settings.manage'),
        steps: [
            {
                target: 'cs-settings-title',
                title: 'Board settings',
                body: "Everything here applies to your department's Customer Service board.",
            },
            {
                target: 'cs-settings-tab-templates',
                title: 'Templates',
                body: 'The library of daily and weekly items you assign from, each with its section, classification (mandatory, production, scheduled…), weight and completion criteria.',
            },
            {
                target: 'cs-settings-tab-notifications',
                title: 'Nightly report recipients',
                body: 'The HOD always gets the close-of-day report. Add anyone else who should, such as the CEO or a deputy.',
            },
            {
                target: 'cs-settings-tab-board',
                title: 'Board rules',
                body: 'Response-time standards used for the service-quality score, the reporting currency for sales, and the calibration period during which scores are shown but must not drive pay or discipline decisions.',
            },
        ],
    },

    // --- SEO ---------------------------------------------------------------
    {
        id: 'seo-employee',
        section: 'Scoring boards',
        title: 'My SEO cards',
        summary: 'Your daily checklist, weekly plan and approved history.',
        where: 'Open your SEO board and switch to Score Based (or My SEO Cards in the menu).',
        audience: (a) => a.isSeoEmployee,
        steps: [
            {
                target: 'emp-tabs',
                title: 'Three tabs, one page',
                body: 'Today is your daily checklist, This Week is your weekly plan, and History has scores your manager has already approved.',
            },
            {
                target: 'emp-points',
                reveal: 'seo-emp-tab-today',
                title: 'Your points at a glance',
                body: "Planned is always 100. Submitted is what you've marked done. Approved only appears once your manager reviews it: submitting isn't scoring.",
            },
            {
                target: 'emp-items',
                reveal: 'seo-emp-tab-today',
                title: "Today's checklist",
                body: 'Grouped by section. Monitoring, implementation and documentation are added automatically every day; your manager fills in the rest each morning.',
            },
            {
                target: 'emp-status',
                reveal: 'seo-emp-tab-today',
                title: 'Update as you work',
                body: "Move a task to In progress, then Submitted when it's ready, or Blocked with a reason if something outside your control is stopping you.",
            },
            {
                target: 'emp-evidence',
                reveal: 'seo-emp-tab-today',
                title: 'Attach your proof',
                body: 'Some tasks need a screenshot, document, or link attached before you can submit them.',
            },
            {
                target: 'emp-history-range',
                reveal: 'seo-emp-tab-history',
                title: 'Check your history anytime',
                body: 'Switch here to see approved scores from past days and weeks. Click any day to see exactly what was recorded.',
            },
        ],
    },
    {
        id: 'seo-hod',
        section: 'Scoring boards',
        title: 'Running the SEO board',
        summary: 'For the HOD: assign and approve daily items, plan weeks, and follow exceptions and trends.',
        where: 'My Department, or the SEO board → Score Based.',
        audience: leadsSeo,
        steps: [
            {
                target: 'hod-tabs',
                title: 'Five views for your team',
                body: 'Today for daily decisions, This Week for plans, Exceptions for anything blocked or rejected, Trends for scores over time, and History for the full record.',
            },
            {
                target: 'hod-today-list',
                reveal: 'seo-hod-tab-today',
                title: 'Assign and decide, right here',
                body: "Click any team member to expand their card. Assign that day's extra tasks until it totals 100 points, then approve, mark late, request a fix, or reject each item they submit.",
            },
            {
                target: 'hod-week-list',
                reveal: 'seo-hod-tab-week',
                title: 'Plan the week ahead',
                body: "Build each person's weekly tasks and approve the plan before the week starts. It also has to total 100 points.",
            },
            {
                target: 'hod-exceptions',
                reveal: 'seo-hod-tab-exceptions',
                title: 'Catch problems early',
                body: 'Anyone with incomplete, late, rejected, or blocked items shows up here automatically, with no need to open every card.',
            },
            {
                target: 'hod-trends',
                reveal: 'seo-hod-tab-trends',
                title: 'See the trend',
                body: 'Compare final weekly scores across your team over 4 or 12 weeks.',
            },
            {
                target: 'hod-history',
                reveal: 'seo-hod-tab-history',
                title: 'Look back on anyone, anytime',
                body: 'Pick a team member and a time range to see their full history, including evidence and your past decisions. Export one person or the whole department as CSV.',
            },
            {
                target: 'hod-settings-link',
                title: 'Manage templates and recipients',
                body: 'Settings is where the task library and who gets the nightly close-of-day report are managed.',
            },
        ],
    },
    {
        id: 'seo-settings',
        section: 'Scoring boards',
        title: 'SEO board settings',
        summary: 'The task library and nightly report recipients.',
        href: '/seo-board/settings',
        audience: (a) => a.has('seo.templates.manage') || a.has('seo.settings.manage'),
        steps: [
            {
                target: 'settings-tabs',
                title: 'Two things live here',
                body: 'The task library your HOD assigns from, and who gets the automatic nightly close-of-day report.',
            },
            {
                target: 'settings-templates',
                reveal: 'seo-settings-tab-templates',
                title: 'The task library',
                body: "Mandatory items are fixed and can't be reweighted here. Production items have a flexible weight range: your HOD picks the exact weight when assigning one to a day.",
            },
            {
                target: 'settings-notifications',
                reveal: 'seo-settings-tab-notifications',
                title: 'Where the nightly report goes',
                body: 'The HOD is included automatically. Add anyone else, like the CEO or a deputy, who should also get the close-of-day email.',
            },
        ],
    },
];
