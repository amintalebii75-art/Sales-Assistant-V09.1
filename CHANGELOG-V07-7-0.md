# V07.7.0 — Settings, Backup and Deployment Readiness

## Added
- Ariana settings dashboard with runtime, role and revision indicators.
- Permission-aware organization settings.
- Backup/recovery center with scoped Recovery exports and server backup summary.
- Deployment-readiness checklist.
- Browser-only interface preferences for compact mode and reduced motion.
- `assets/css/v07-settings-deployment.css`.
- `assets/js/v07-settings-deployment.js`.

## Security
- Added Apache security headers when `mod_headers` is available.
- Added `DirectoryIndex` and repository-directory blocking.
- Kept sensitive file extension denial.
- Release intentionally omits `create_admin.php` and real `config.php`.

## Fixed
- Repaired malformed V07 CSS links in `index.php`.
- Updated visible version badge to V07.7.

## Unchanged
- Database schema and migrations.
- RBAC and Customer Access backend rules.
- Planning and Jalali date contracts.
- Backup/Restore API semantics.
