# Data retention

Target retention periods for tables that grow unbounded and aren't already covered by an existing deletion path (task/board soft-deletes, user deactivation). These are policy targets, not yet an enforced purge job — see "Enforcement" below for what that would require.

| Data | Table(s) | Target retention | Why |
| --- | --- | --- | --- |
| Audit log | `audit_logs` | 2 years, then archive (export + delete) rather than delete outright | Immutable by DB trigger (`make_audit_logs_immutable` migration) — the audit trail is the point; don't shorten this without a compliance/legal reason. |
| Daily/weekly report snapshots | `report_snapshots`, `report_deliveries` | 1 year | Immutable by DB trigger (see `create_report_snapshots_table` migration) — a report must read exactly as it was sent even after this window, for as long as it's kept at all. |
| Notifications | `notifications` | 90 days for read notifications; unread notifications are kept until read | Laravel's standard notifications table; unbounded growth otherwise, but a notification a user hasn't seen yet shouldn't disappear on a timer. |
| Sessions | `sessions` (if `SESSION_DRIVER=database` is actually provisioned — see the open item below) | Session lifetime only (`SESSION_LIFETIME`, currently 120 minutes) | Sessions have no long-term value once expired; the driver/store should already be evicting these. |
| Failed jobs | `failed_jobs` | 90 days | Needed for the queue-health screen's recent history (`/admin/queue-health`); older entries have no operational value once the underlying failure is understood and fixed. |
| Attachments | `attachments` + underlying files | Follow the parent task/ticket's own lifecycle — no independent timer | Deleting an attachment while its parent record survives (audit history, other comments) would create a dangling reference; only purge attachments whose parent has been gone long enough to also be past its own retention. |

## Known gap: no `sessions` table migration

`config/session.php` defaults to `database`, and `.env.example` sets `SESSION_DRIVER=database`, but no migration creates a `sessions` table in this codebase — found while building session invalidation (`InvalidateStaleSessions`). If that driver is genuinely in use in production, session writes are failing silently or falling back to something else; this needs its own fix (add the migration, or change the documented default) independent of retention policy.

## Enforcement

None of the above is automated yet. Implementing it means a scheduled command per table, each respecting that table's constraints:

- `audit_logs` and `report_snapshots`/`report_deliveries` are immutable by trigger — a purge job for these needs `DELETE` explicitly allowed past the retention window (e.g. a trigger condition on row age, or a maintenance-mode bypass), not a blanket relaxation of the trigger.
- Run purges outside business hours and log what was purged (count + date range), so a retention job silently deleting more than intended is itself auditable.
