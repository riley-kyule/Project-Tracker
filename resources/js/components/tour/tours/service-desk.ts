import { type TourDefinition } from '../types';

const handlesTickets = (a: { has: (p: string) => boolean }) => a.has('tickets.manage');

export const serviceDeskTours: TourDefinition[] = [
    {
        id: 'tickets',
        section: 'Service Desk',
        title: 'The Service Desk',
        summary: 'Asking IT or R&D for help, and following your requests.',
        href: '/tickets',
        steps: [
            {
                target: 'ticket-new',
                title: 'Ask for help',
                body: 'Click New ticket, choose a category and which team should handle it (IT, R&D or both), describe what happened and how badly it affects your work. The more detail, the faster the fix.',
            },
            {
                target: 'ticket-new',
                title: 'Filing for someone else',
                body: "You can also file a ticket on a colleague's behalf: pick them as the Requester and they'll get the updates.",
                when: (a) => a.isExec || a.hasRole('IT Technician'),
            },
            {
                target: 'ticket-list',
                title: 'Track your tickets',
                body: 'Every ticket gets a number (TK-12). The status shows where it is: New, Assigned, In Progress, Waiting for User (they need something from you), Resolved or Closed. Click a column heading to sort, or a title to open it.',
            },
            {
                target: 'ticket-toolbar',
                title: 'Work the queue',
                body: 'As a technician you see the whole queue for your team. Filter by status, priority, team or who it is assigned to; choose Unassigned to pick up new work.',
                when: handlesTickets,
            },
        ],
    },
    {
        id: 'ticket',
        section: 'Service Desk',
        title: 'Inside a ticket',
        summary: 'Replies, attachments, status changes and, for technicians, assigning, resolving and converting to tasks.',
        where: 'Open any ticket from the Service Desk.',
        steps: [
            {
                target: 'ticket-status',
                title: 'Where it stands',
                body: 'The current status and priority. If a ticket waits on the requester for too long without a reply, it closes automatically; you can then confirm it is resolved or reopen it.',
            },
            {
                target: 'ticket-actions',
                title: 'Technician actions',
                body: 'Assign a technician, move the status along (In Progress, Waiting for User, Escalated…), Resolve it with how it was fixed and time spent, or Convert to task when it turns into bigger work on a board.',
                when: handlesTickets,
            },
            {
                target: 'ticket-details',
                title: 'The details',
                body: 'Who asked, their department, the team handling it, the category and assignee. Due and First response show the service-level deadlines for this priority.',
            },
            {
                target: 'ticket-responses',
                title: 'Talk it through',
                body: 'Reply here instead of chasing people by chat: everyone on the ticket is notified.',
            },
            {
                target: 'ticket-responses',
                title: 'Internal notes',
                body: 'Tick "Internal note" to leave a note only technicians can see. The requester never sees it.',
                when: handlesTickets,
            },
            {
                target: 'ticket-attachments',
                title: 'Screenshots and files',
                body: 'Attach a screenshot or document so the problem is easy to see.',
            },
            {
                target: 'ticket-history',
                title: 'Status history',
                body: 'Every status change, who made it and when.',
            },
        ],
    },
];
