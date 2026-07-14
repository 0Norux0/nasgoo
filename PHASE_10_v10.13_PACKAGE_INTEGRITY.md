# Phase 10 v10.13 — Package Integrity Report

Per dev §12.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 10 v10.13`
- No nested duplicate project
- Laravel ^11.0, Inertia ^1.x, React 18, Tailwind v3

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.13.tar.gz
marketplace-phase-10-v10.13.tar.gz.sha256

marketplace-phase-10-v10.13.zip
marketplace-phase-10-v10.13.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.13.tar.gz.sha256
```

## File-level SHA-256 of v10.13-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `466770b009863779ebeb6207d638f85b3f1ed3541e13c15ab8e41e7476141e92` |
| 2 | `resources/js/Layouts/VendorLayout.tsx` | `b5df00121ea33a9d5e9dcab08d20fc02d17ab672222061b1d6e95dabde261d97` |
| 3 | `resources/js/Pages/Vendor/Dashboard.tsx` | `fe01a09f0ac0d7426f7cbd5acd869b2531e46fb90fa859ef9cab649db850a5af` |
| 4 | `tests/Feature/Phase10V1013RegressionTest.php` | `5afb0821d21f40012a5f3f0a0bba1e82c8e3f3c62ece14a1e169818a0c363f92` |
| 5 | `.github/workflows/ci.yml` | `9c3d094fb3d80dcf3eb57d6b554a6df1258be834b879d3be2c23c046b4539d8f` |

## Leak check

Excludes from the archive:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules`
- `marketplace/vendor`
- `marketplace/.git`
- `marketplace/tsconfig.verify.json`

## §12 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1013v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.13`
3. **VendorLayout** in archive contains:
   - `vendor-nav-reports` testid (twice: desktop nav + mobile nav)
   - `ReportsIcon` SVG component
   - `icon: 'reports'` flag on the Reports nav item
   - `isActive` helper
   - Reports in `baseItems` (always visible to vendors)
   - Indigo active-state styling (`text-indigo-700`)
4. **Vendor Dashboard** in archive contains:
   - `vendor-dashboard-reports-cta` testid
   - Link to `/vendor/reports`
   - "View My Reports" CTA copy
5. **Vendor Reports route + controller + page** present:
   - `routes/web.php`: `vendor.reports.index` route in `vendor:approved` middleware group
   - `app/Http/Controllers/Vendor/VendorReportsController.php` present
   - `resources/js/Pages/Vendor/Reports/Index.tsx` present
6. **ReportsService** vendor-scoped methods present (no v10.12 SQL regressions)
7. Pest test file `Phase10V1013RegressionTest.php` present (19 scenarios)
8. CI YAML valid + 3 new v10.13 sub-checks
9. SHA-256 of all v10.13-touched files match between workspace and archive
10. No node_modules / vendor / .git / tsconfig.verify in archive
11. No nested duplicate project folder
12. All v10.0-v10.12 preservation markers intact:
    - v10.10 `guardAdminReportsAccess` (admin reports — untouched)
    - v10.10 DiagnoseReportsAccessCommand, RepairAdminReportsAccessCommand, EnsureAdminReportsAccessSeeder
    - v10.11 §5 `SUM(requested_amount_minor)` (2 sites)
    - v10.11 §3 `computeStatusOptions`
    - v10.11 §4 5 defensive `messages.user:id,name,email` eager-loads
    - v10.11 §2 no `getAllPermissions` in default Inertia share
    - v10.12 `User::role('customer')` Spatie scope
    - v10.12 no `DB::table('users')->where('role',...)` in app/
