# Phase 10 v10.9 — Patch Notes

## What's fixed

| Defect | Root cause | Fix |
|---|---|---|
| Admin user sees the "Reports Dashboard" menu link in the Filament panel, but clicking it returns `403 — This action is unauthorized.` | Two independent auth rules guarded the same surface: Filament navigation used `hasAnyRole(['super_admin','admin_staff'])` (role check) while the `/admin/reports` controller used a Gate that called `$user->hasPermissionTo('reports.view')` (permission check). On any installation where the permission row drifted from the role assignment — stale Spatie cache, pre-Phase-10 DB that never re-seeded after `reports.view` was added to the catalogue, guard mismatch, custom permission management — the role check passed (link visible) but the permission check failed (route 403). | (1) NEW `User::canManageAdminReports()` — single canonical rule (role-based with inactive-user exclusion). (2) `Gate::define('viewReports')` rewritten to call the canonical helper. (3) NEW `Gate::before` granting super_admin every ability (Laravel pattern, defense in depth). (4) Filament navigation `->visible()` calls the canonical helper. Menu and route ENFORCE THE SAME RULE — by construction. (5) Self-healing data migration ensures `reports.view` exists + super_admin/admin_staff hold it, with `permission:cache-reset` baked in. (6) 16 Pest scenarios + 4 CI sub-checks enforcing the wiring. |

## Counts

| | v10.8 → v10.9 |
|---|---|
| Phase 10 CI sub-checks | 42 → 46 |
| Phase 10 Pest scenarios | 104 → 120 |
| Phase-specific CI grand total | 97 → 101 |
| New PHP source files | 1 (self-healing migration) |
| Modified PHP source files | 3 (User, AppServiceProvider, Filament AdminPanelProvider) |
| New Pest test files | 1 (16 scenarios) |
| Modified React files | 0 |
| v1-v9 files touched | 0 |
| v10.0-v10.8 fix code reverted | 0 |
| Helpers added | 3 (`p109_admin`, `p109_vendor`, `p109_customer`, `p109_seed_roles_and_permissions`) — 60 total unique, 0 duplicates |

## tsc verification

No React files changed in v10.9. The v10.8 tsc-clean state (exit 0 across all 12 v10.x React files) is preserved.

## Required access rules — verified by tests

| Role | Menu visible | `/admin/reports` |
|---|---|---|
| `super_admin` (active) | ✓ | 200 |
| `admin_staff` (active) | ✓ | 200 |
| Inactive admin | ✗ | 403 |
| `vendor` | ✗ | 403 |
| `customer` | ✗ | 403 |
| Guest | ✗ | redirect /login |

## Per §O acceptance

**Phase 10 v10.9 is implemented but requires developer runtime verification.**

Per dev §10 + §14: this isn't fixed until the admin route is manually opened after logout/login. PHASE_10_v10.9_DEVELOPER_CHECKLIST.md lists the confirmations.
