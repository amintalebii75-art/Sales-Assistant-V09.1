# V07.8.0 — Integrated Pilot Smoke Test

## Added
- Authenticated `pilot_test.php` for non-destructive environment diagnostics.
- Automatic checks for PHP, PDO MySQL, config, session, CSRF, team linkage, DB, required tables, app-state revision and V07 assets.
- Browser checks for API scope, LocalStorage, IndexedDB, responsive viewport and authorized routes.
- Four-role manual checklist with PASS / FAIL / BLOCKED / NOT RUN.
- Per-role local persistence and downloadable JSON report.
- Release gate that stays HOLD until every manual test passes.

## Navigation
- Added Pilot Test link to desktop and mobile navigation.
- Added Pilot Test link to manager-viewer summary.
- Updated visible version badge to V07.8.

## Security
- Diagnostics are read-only and do not reveal secrets, CSRF tokens or Full-State content.
- No database migration and no destructive actions.
