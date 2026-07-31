# Release process

CHANGELOG.md already tracks changes under an `## Unreleased` heading as they land — this doc is the missing other half: how that becomes a tagged, deployable release.

## Cutting a release

1. Confirm `main` is green (CI passing) and the "Release verification" checklist in `docs/DEPLOYMENT.md` has been run against staging.
2. In `CHANGELOG.md`, rename `## Unreleased` to `## [X.Y.Z] - YYYY-MM-DD` and start a fresh empty `## Unreleased` above it for whatever lands next.
3. Commit that as `chore: release vX.Y.Z`.
4. Tag it and push the tag: `git tag -a vX.Y.Z -m "vX.Y.Z"` then `git push origin vX.Y.Z`.
5. Deploy that tagged commit using the sequence in `docs/DEPLOYMENT.md`.

## Versioning

Semantic versioning (`MAJOR.MINOR.PATCH`): increment `MAJOR` for a breaking data/API change, `MINOR` for new user-facing functionality, `PATCH` for fixes only. This is an internal system with one deployment target, not a versioned public API — the number mainly exists so an incident report, a rollback, or a support conversation can reference "what was running" unambiguously.

## Why both a tag and a Deployment record

The git tag says what *should* be running; `app/Jobs/DeployLatestRelease.php` already records what actually got deployed — `commit_before`/`commit_after` on each `Deployment` row, visible via `/admin/deployments` (self-deploy) or deploy logs (Compose deploy). If those two disagree (a tag exists but the last recorded deploy's `commit_after` predates it), that's the signal a deploy didn't go out as expected — check the deployment history before assuming the tag reflects production.

## Rollback

Roll back only to a database-compatible prior tag — review every migration's `down()` between the current and target commit first, per `docs/DEPLOYMENT.md`'s release-verification checklist. A tag with no safe `down()` path back from the current schema should be rolled forward (fix and re-release), not rolled back to.
