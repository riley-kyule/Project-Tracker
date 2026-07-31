# Backup restoration drill

`docs/SECURITY.md` already commits to alerting on backup failures and backing up PostgreSQL and private attachments together. This is the missing other half: proving, on a schedule, that a backup can actually be restored — a backup nobody has restored from is unverified.

## Why this is separate from "backups run successfully"

A backup job reporting success only proves the job ran, not that the resulting file is restorable, complete, or paired correctly with the attachments taken at the same point in time. The only way to know a backup actually works is to restore it somewhere and check.

## Drill procedure

Run quarterly, or immediately after any change to the backup mechanism itself (storage target, encryption, schedule).

1. Pick a recent backup (database dump + attachments archive from the same point in time — they must be restored together, not independently, since a task row referencing an attachment that wasn't captured at the same instant is a broken reference either direction).
2. Restore both into an isolated environment that cannot reach production services (mail, EPE push, BigQuery, Google SSO) — point `MAIL_MAILER=log`, disable outbound integrations, and use a separate `APP_URL`/database before restoring, so the drill can't send real notifications or mutate real external state.
3. Run `php artisan migrate --force` against the restored database and confirm it completes with no errors (this also validates that the backup's schema version and the current migration set are reconcilable).
4. Verify representative data end-to-end: log in as a seeded/known account, open a task with attachments and confirm the attachment downloads and matches its checksum, open a resolved ticket and confirm its history, and spot-check `audit_logs` for a known event around the backup's timestamp.
5. Record: how long the restore took end-to-end, what (if anything) failed or required a manual step, and the backup's age at drill time (how far behind "now" it was).
6. Fix anything the drill surfaced before the next scheduled drill, not after — a known-broken restore path is the actual incident, not a future task.

## What "pass" means

The restored environment is usable — a real user's real data is there, attachments open, and nothing required undocumented manual intervention beyond the documented restore commands. A drill that "mostly worked" with an unlisted manual fix is a fail: fix the procedure (or the backup process) until it isn't.
