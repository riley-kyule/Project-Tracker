# Security model

## Access control

EWMS is an internal, single-company system. Public registration and self-service account deletion are disabled by default; administrators provision and deactivate workforce accounts. Record access is enforced with Laravel policies, and client-provided actor or department identity is not trusted.

Restricted board membership controls task visibility. Assignees must be active and able to view the destination board. Ticket-to-task conversion applies the same task creation policy as direct task creation. `Task::scopeVisibleTo()` mirrors `TaskPolicy::view()` for list/count endpoints (dashboards, reports) that can't authorize row-by-row without a prohibitive N+1 cost — keep the two in sync by hand when either changes.

A role or status change stamps `users.sessions_invalidated_at`; `InvalidateStaleSessions` middleware forces re-authentication on that user's next request if their session predates the change, so an already-open session can't keep acting on revoked access until it naturally expires.

## Audit and integrity

Critical workflows write actor, event, before/after values, IP address, user agent, and UTC time to `audit_logs`. Database triggers make those rows immutable. Multi-record workflows and their audit entries execute in transactions, while notifications are dispatched after persistence.

Account deactivation is preferred to deletion so authorship, ticket history, and audit attribution remain intact.

## Browser and session protections

- CSRF protection applies to web mutations.
- Login and recovery endpoints are rate limited.
- Authenticated responses use private, no-store caching.
- Responses set clickjacking, MIME-sniffing, referrer, browser-permission, and HTTPS transport headers.
- Production passwords require at least 12 characters with upper/lowercase letters, a number, and a symbol.
- Database session payload encryption is enabled in the example configuration.

## Attachments

Attachments are stored privately and inherit authorization from their parent task or ticket. Uploads are limited to 25 MB, use an allowlist (SVG is deliberately excluded — it can carry embedded scripts, a stored-XSS risk if ever rendered inline), compare detected content MIME to the extension, reject executable formats, and record a SHA-256 checksum. The stored filename is stripped of path segments and control characters before being used as the download's `Content-Disposition` name. This is defense in depth, not a substitute for malware scanning.

## Report and CSV exports

Spreadsheet cells built from user-controlled text (ticket titles, category/department names, resolution methods) are escaped before CSV export (`ReportController::csvSafe()`) — a cell beginning with `=`, `+`, `-`, or `@` is prefixed with a single quote so Excel/Sheets treats it as a literal string instead of evaluating it as a formula when the export is opened.

## Operational responsibilities

- Terminate TLS with modern protocols and keep `APP_DEBUG=false` in production.
- Rotate application, database, Redis, mail, and integration secrets through the deployment platform.
- Run Composer and npm advisory checks in CI and patch supported runtime versions promptly.
- Alert on repeated login lockouts, authorization failures, queue failures, unexpected audit gaps, and backup failures without creating employee-surveillance rankings.
- Limit database access so the application role cannot disable audit triggers.
- Perform periodic role and restricted-board membership reviews.

Report suspected vulnerabilities privately to the system owner or internal security contact; do not place secrets or sensitive evidence in a public issue tracker.
