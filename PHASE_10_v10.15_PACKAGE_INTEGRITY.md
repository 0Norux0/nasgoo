# Phase 10 v10.15 — Package Integrity Report

Per dev §20.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 10 v10.15`
- No nested duplicate project
- Laravel ^11.0, Inertia ^2.0, React 18

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.15.tar.gz
marketplace-phase-10-v10.15.tar.gz.sha256
marketplace-phase-10-v10.15.zip
marketplace-phase-10-v10.15.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.15.tar.gz.sha256
```

## File-level SHA-256 of v10.15-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `d928507956146b19c567689eb7bfdad4b5bad38cb23fead06438e2caedc3e0c3` |
| 2 | `app/Http/Middleware/HandleInertiaRequests.php` | `2404796b6d3eb2d7cf86656019a61425404e41ef1af48eb4380377a23a13d369` |
| 3 | `app/Http/Controllers/HomeController.php` | `4b9e9a91d5c8d0027d42aa1639a60b65eb14bd7a7338e4c78c80b511428b9cc5` |
| 4 | `tests/Feature/Phase10V1015RegressionTest.php` | `f78b42992555b5a78541865463aac77bf68e82c28a86b03297c00a1680992f19` |
| 5 | `.github/workflows/ci.yml` | `a54661fb487117b10230bcb5653696c23fd24834a4b5832c25986a6a71f8c7f2` |

## Leak check

Excludes from archive:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules`
- `marketplace/vendor`
- `marketplace/.git`
- `marketplace/tsconfig.verify.json`

## §20 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1015v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.15`
3. **HandleInertiaRequests** in archive:
   - 5 defensive catch markers: `auth.user`, `cart_summary`, `top_categories`, `translations cache`, `app.version cache`
   - v10.14 scope-aware path checks (admin/, vendor/) — 2 each, PRESERVED inside try block
4. **HomeController** in archive:
   - Defensive `homepage health cache failed (Phase 10 v10.15 defensive catch)` marker
   - v10.14 `marketplace:homepage_health:v1` cache key — PRESERVED inside try block
5. **v10.14 indexes migration** — UNTOUCHED (same SHA-256 as v10.14)
6. Pest test file `Phase10V1015RegressionTest.php` present (20 scenarios)
7. CI YAML valid + 3 new v10.15 sub-checks
8. SHA-256 of all v10.15-touched files match between workspace and archive
9. No node_modules / vendor / .git / tsconfig.verify in archive
10. No nested duplicate project folder
11. **All v10.0-v10.14 preservation markers intact**:
    - v10.10 `guardAdminReportsAccess` (3 occurrences in ReportsController)
    - v10.10 DiagnoseReportsAccessCommand, RepairAdminReportsAccessCommand, EnsureAdminReportsAccessSeeder
    - v10.11 §5 `SUM(requested_amount_minor)` (2 sites)
    - v10.11 §3 `computeStatusOptions`
    - v10.11 §4 5 defensive `messages.user:id,name,email` eager-loads
    - v10.11 §2 no `getAllPermissions` in default Inertia share
    - v10.12 `User::role('customer')` Spatie scope
    - v10.13 `ReportsIcon` SVG + `vendor-nav-reports` + `vendor-dashboard-reports-cta`
    - v10.14 scope-aware closures (admin/ + vendor/ path checks, 2 each)
    - v10.14 perf indexes migration
