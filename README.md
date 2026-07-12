# Project Tracker

An internal operations platform that centralizes work management and IT support for a growing company. One system answers the questions every team asks daily: what must be done, who owns it, when it is due, what is blocked, and what was delivered.

> If it is not on the board, it does not exist.

## What it does

### Work management
- Department Kanban boards with configurable, drag-and-drop columns
- Tasks with owners, priorities, deadlines, checklists, labels, and progress
- Comments, @mentions, file attachments, and a full activity timeline
- Executive-priority flagging and exception-first dashboards

### Internal service desk
- IT ticket submission with categories, priorities, and SLA timestamps
- Ticket assignment, lifecycle tracking, and internal technician notes
- Ticket-to-task conversion for requests that become planned work
- Remote / office / onsite resolution tracking and support metrics

### Management visibility
- Executive overview: due today, overdue, blocked, awaiting review, at risk
- Per-employee and per-department workload views
- Drill-down from every metric to the underlying records
- Immutable audit history for critical changes

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel (modular monolith) |
| Frontend | React + TypeScript via Inertia.js |
| UI | Tailwind CSS, shadcn/ui, dnd-kit |
| Database | PostgreSQL |
| Cache & queues | Redis |
| Real-time | Queue-backed notifications; Reverb is planned |
| Testing | PHPUnit, ESLint, TypeScript, and Prettier |

## Roadmap

| Phase | Focus |
|---|---|
| 1 | Core work management, collaboration, service desk, dashboards |
| 2 | Projects, recurring tasks, dependencies, time tracking, approvals |
| 3 | Analytics integrations and executive reporting |
| 4 | Automation rules and AI assistance |

## Status

Active MVP development. Authentication, roles, departments, boards, task collaboration,
notifications, service desk workflows, dashboards, reports, and global search are implemented
with feature coverage. Projects and registry work remains on the roadmap.

## Principles

- Optimize for speed and daily adoption, not feature count
- Every critical mutation is authorized and audited
- Persist first, broadcast second — real-time is a convenience, never a dependency
- No employee surveillance; metrics stay contextual and reviewable
