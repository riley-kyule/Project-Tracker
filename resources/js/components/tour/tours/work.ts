import { type TourDefinition } from '../types';

export const workTours: TourDefinition[] = [
    {
        id: 'dashboard',
        section: 'Work',
        title: 'Your dashboard',
        summary: 'Your home page: what is due, what is waiting on you, and a fast way to add a task.',
        href: '/dashboard',
        steps: [
            {
                target: 'dashboard-quick-add',
                title: 'Quick add a task',
                body: 'Jot down a task in seconds: give it a title, pick a board and (optionally) a due date. It is assigned to you, and you can fill in the details later.',
            },
            {
                target: 'dashboard-stats',
                title: 'Your numbers today',
                body: 'Your open, due, overdue and blocked tasks, plus what is awaiting review and what you have finished. Red numbers need attention first.',
            },
            {
                target: 'dashboard-my-work',
                title: 'My work',
                body: 'Every open task assigned to you, with its stage, priority and due date. Overdue dates turn red. Click a title to open its board.',
            },
            {
                target: 'dashboard-waiting',
                title: 'Waiting on me',
                body: 'Tasks where someone asked you to approve their work. Open one and choose Approve or Send back.',
            },
            {
                target: 'dashboard-mentions',
                title: 'Mentions',
                body: 'The latest comments where someone @mentioned you, so you never miss a question.',
            },
            {
                target: 'dashboard-tickets',
                title: 'My open tickets',
                body: 'Support tickets you raised (or that are assigned to you) that are still open, with their current status.',
            },
        ],
    },
    {
        id: 'boards',
        section: 'Work',
        title: 'Boards',
        summary: 'Where every board you can see is listed, and how new boards are made.',
        href: '/boards',
        steps: [
            {
                target: 'boards-grid',
                title: 'Your boards',
                body: "A board holds a team's tasks as cards in columns. Each tile shows its department, how many tasks it has and who can see it: Company (everyone), Department (that department) or Restricted (invited people only). Click one to open it.",
            },
            {
                target: 'boards-new',
                title: 'Create a board',
                body: 'Give it a name, choose its department (or company-wide) and who can see it. It starts with sensible default columns you can change later.',
                when: (a) => a.has('boards.manage'),
            },
        ],
    },
    {
        id: 'board',
        section: 'Work',
        title: 'Working on a board',
        summary: 'Columns, cards, drag and drop, filters and saved filters.',
        where: 'Open any board from Boards.',
        steps: [
            {
                target: 'board-column-name',
                title: 'Columns are stages',
                body: 'Work moves left to right. Click a column name to see what it means: for example, moving a card into a Completed column marks the task done.',
            },
            {
                target: 'board-column-count',
                title: 'Task count and limit',
                body: 'How many tasks are in the column. If the column has a work-in-progress limit it shows as "5 / 4" and turns red when the column is over it.',
            },
            {
                target: 'board-task-card',
                title: 'Tasks are cards',
                body: 'A card shows the priority, due date (red when overdue) and whose it is. A lock means it is waiting on another task; a star is a CEO priority. Click to open it, or drag it to another column to move it.',
            },
            {
                target: 'board-add-task',
                title: 'Add a task',
                body: 'Type a title and press Enter. The task lands in this column; open it afterwards to add details.',
            },
            {
                target: 'board-filters',
                title: 'Find tasks fast',
                body: 'Search by title or filter by person and priority. While a filter is on, cards can only be reordered within a column: clear it to move cards between columns.',
            },
            {
                target: 'board-save-filter',
                title: 'Save a filter',
                body: 'Save the current search and filters under a name. It appears as a chip above the board, so one click brings it back. The x on a chip deletes it.',
            },
            {
                target: 'board-view-tabs',
                title: 'Score Based view',
                body: 'This team also uses daily score cards. Switch to Score Based to see them; switch back to Kanban for the task board.',
            },
        ],
    },
    {
        id: 'board-manage',
        section: 'Work',
        title: 'Managing a board',
        summary: 'Bulk actions, columns and work-in-progress limits, for people who manage the board.',
        where: 'Open a board you manage.',
        audience: (a) => a.has('boards.manage'),
        steps: [
            {
                target: 'board-select',
                title: 'Change many tasks at once',
                body: 'Click Select, tick the cards you want, then use the bar that appears: move them to a column, assign them, add a collaborator, ask for approval, set them to auto-reset, duplicate or delete them.',
            },
            {
                target: 'board-column-menu',
                title: 'Arrange columns',
                body: 'Use the arrows to reorder a column. The ⋮ menu edits its name, type and work-in-progress limit, or deletes it (a column must be empty first).',
            },
            {
                target: 'board-add-column',
                title: 'Add a column',
                body: 'Pick a column type so the system knows what it means: Active starts work (and checks prerequisites), Review holds work awaiting approval, Completed finishes tasks, Archived hides them. Custom has no special behaviour.',
            },
        ],
    },
    {
        id: 'task',
        section: 'Work',
        title: 'Task details',
        summary: 'Editing a task: who, when, priority, progress and labels. Everything saves as you type.',
        where: 'Open a board and click any card.',
        steps: [
            {
                target: 'task-header',
                title: 'It saves by itself',
                body: 'Every task has a number (T-123) you can search for. There is no Save button: changes save as you go. Watch for "Saved" here, or "Couldn\'t save, retry" if your connection drops.',
            },
            {
                target: 'task-fields',
                title: 'Who, how urgent, when',
                body: 'Assign the task to anyone (even outside this department; that gives them access), set its priority and due date, and move it to another column from here.',
            },
            {
                target: 'task-progress',
                title: 'Progress',
                body: 'Slide to show how far along it is. If the task has a checklist, progress follows the ticked items instead. At 100% press Mark as Completed.',
            },
            {
                target: 'task-labels',
                title: 'Labels',
                body: "Tick labels to categorise the task. The first label's colour also shows as a strip across the top of the card.",
            },
            {
                target: 'task-ceo-priority',
                title: 'CEO priority',
                body: 'Flag a task the company must not let slip. It gets a star on the card and appears in the CEO Priority list on the CEO dashboard.',
            },
            {
                target: 'task-advanced',
                title: 'Advanced options',
                body: 'Open Advanced for: extra people (collaborators and watchers), confidentiality, related tasks, linking a project, making the task repeat on a schedule, and auto-reset (putting this same task back on the board every day, week or month).',
            },
        ],
    },
    {
        id: 'task-collab',
        section: 'Work',
        title: 'Collaborating on a task',
        summary: 'Checklists, comments and mentions, prerequisites, time, files, links and approvals.',
        where: 'Open any task (this tour plays the second time you open one).',
        steps: [
            {
                target: 'task-checklists',
                title: 'Checklists',
                body: "Break the work into steps. Ticking items updates the task's progress automatically. Save a checklist as a template to reuse it on other tasks with one click.",
            },
            {
                target: 'task-comments',
                title: 'Comments and mentions',
                body: 'Discuss the task here. Reply to a specific comment, or use @ Mention to notify people directly. You can edit your own comment for 10 minutes after posting, and delete it any time.',
            },
            {
                target: 'task-dependencies',
                title: 'Prerequisites',
                body: 'Tasks that must be finished before this one can start. Moving it into an Active column while they are open is blocked; a manager can override that with a reason.',
            },
            {
                target: 'task-time',
                title: 'Time tracking',
                body: 'Start the timer when you begin and stop it when you pause. Forgot? Log time manually with a reason; a manager approves manual entries.',
            },
            {
                target: 'task-attachments',
                title: 'Files',
                body: 'Upload documents, screenshots or other files (up to 25 MB each). Anyone who can see the task can download them.',
            },
            {
                target: 'task-links',
                title: 'Links',
                body: 'Add web links (a Google Doc, a page, a design) with an optional label so everyone finds the right resource.',
            },
            {
                target: 'task-approval',
                title: 'Approval',
                body: 'Need sign-off? Choose an approver and request approval. They can Approve or Send back with a note. A task awaiting approval cannot be moved to Completed.',
            },
            {
                target: 'task-activity',
                title: 'Activity',
                body: 'A record of everything that happened to this task and who did it.',
            },
            {
                target: 'task-duplicate',
                title: 'Duplicate',
                body: 'Make a copy of this task (handy for repeating work with the same checklist).',
            },
            {
                target: 'task-danger',
                title: 'Delete',
                body: 'Permanently removes the task. Only people allowed to delete it see this, and it cannot be undone.',
            },
        ],
    },
    {
        id: 'projects',
        section: 'Work',
        title: 'Projects',
        summary: 'Group related tasks across boards and departments and track overall progress.',
        href: '/projects',
        steps: [
            {
                target: 'projects-grid',
                title: 'All projects',
                body: 'Each tile shows the owner, number of tasks, progress bar, health (on track, at risk, off track), status and deadline. A warning sign means it is at risk or overdue.',
            },
            {
                target: 'projects-new',
                title: 'Start a project',
                body: "Name it, choose an owner and home department, and set a deadline. Then link tasks to it from the project page or from a task's Advanced section.",
                when: (a) => a.has('projects.manage'),
            },
        ],
    },
    {
        id: 'project',
        section: 'Work',
        title: 'A project page',
        summary: 'Status, health, people, departments and linked tasks for one project.',
        where: 'Open any project from Projects.',
        steps: [
            {
                target: 'project-status',
                title: 'Status and health',
                body: 'Keep these current: status says where the project is (planned, active, on hold, completed, cancelled); health says whether it is on track.',
            },
            {
                target: 'project-summary',
                title: 'At a glance',
                body: 'Owner, home department, deadline and progress, plus links to the boards the project uses.',
            },
            {
                target: 'project-people',
                title: 'People',
                body: 'Add anyone involved beyond the owner, from any department. Adding them gives them access to the project.',
            },
            {
                target: 'project-departments',
                title: 'Departments',
                body: 'Add whole departments that take part, beyond the home department.',
            },
            {
                target: 'project-tasks',
                title: 'Linked tasks',
                body: 'Every task that belongs to this project, wherever its board is. Link an existing task here, or unlink one that no longer belongs.',
            },
        ],
    },
    {
        id: 'search',
        section: 'Work',
        title: 'Search',
        summary: 'Find any task, ticket, board or person you are allowed to see.',
        href: '/search',
        steps: [
            {
                target: 'search-box',
                title: 'Search everything',
                body: 'Type at least two letters. Results are grouped into tasks, tickets, boards and people. Know the number? Type T-42 for a task or TK-7 for a ticket.',
            },
        ],
    },
];
