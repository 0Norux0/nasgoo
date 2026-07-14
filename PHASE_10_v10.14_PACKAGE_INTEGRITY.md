# Phase 10 v10.14 — Package Integrity Report

Per dev §20.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 10 v10.14`
- No nested duplicate project
- Laravel ^11.0, Inertia ^2.0, React 18

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.14.tar.gz
marketplace-phase-10-v10.14.tar.gz.sha256

marketplace-phase-10-v10.14.zip
marketplace-phase-10-v10.14.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.14.tar.gz.sha256
```

## File-level SHA-256 of v10.14-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `7092d92e8d30d662d8e0a3b13b41b56f22d6d10174832c5cb1b373c125212542` |
| 2 | `app/Http/Middleware/HandleInertiaRequests.php` | `6ee63af1257ccd7ad0b93cd979e279eae6cfa690cf49c99c1f532ff193747c09` |
| 3 | `app/Http/Controllers/HomeController.php` | `594203240f17f331583b5708490602fb2af4ff40025fd49e97a4697e47462f86` |
| 4 | `database/migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php` | `d3d727add9346a89439c02a761fe8aa4c5ab259170d13cab7dab994b07b4119e` |
| 5 | `tests/Feature/Phase10V1014RegressionTest.php` | `2f6ceb7df5d3d80ad91682cb51c6b5702bb2c0a751598c9dbf169982276238a4` |
| 6 | `.github/workflows/ci.yml` | `e015558b736f8b6ff634ef0b8727ac9817d2dd2d6acab33842ce793574e1f70e` |

## Leak check

Excludes from archive:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules`
- `marketplace/vendor`
- `marketplace/.git`
- `marketplace/tsconfig.verify.json`

## §20 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1014v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.14`
3. **HandleInertiaRequests** in archive:
   - "Phase 10 v10.14 §4 PERFORMANCE" marker comments
   - `str_starts_with($path, 'admin/')` skip in BOTH `cart_summary` AND `top_categories` closures (2 occurrences)
   - `str_starts_with($path, 'vendor/')` same (2 occurrences)
4. **HomeController** in archive:
   - `marketplace:homepage_health:v1` cache key
   - `addSeconds(30)` TTL
5. **v10.14 indexes migration** present in `database/migrations/`:
   - 8 new composite indexes
   - `hasIndex()` idempotency guard
   - All index names ≤ 64 chars (MySQL identifier limit)
6. Pest test file `Phase10V1014RegressionTest.php` present (15 scenarios)
7. CI YAML valid + 4 new v10.14 sub-checks
8. SHA-256 of all v10.14-touched files match between workspace and archive
9. No node_modules / vendor / .git / tsconfig.verify in archive
10. No nested duplicate project folder
11. **All v10.0-v10.13 preservation markers intact**:
    - v10.10 `guardAdminReportsAccess` (3 occurrences in ReportsController)
    - v10.10 DiagnoseReportsAccessCommand, RepairAdminReportsAccessCommand, EnsureAdminReportsAccessSeeder
    - v10.11 §5 `SUM(requested_amount_minor)` (2 sites)
    - v10.11 §3 `computeStatusOptions`
    - v10.11 §4 5 defensive `messages.user:id,name,email` eager-loads
    - v10.11 §2 no `getAllPermissions` in default Inertia share (still 0 occurrences)
    - v10.12 `User::role('customer')` Spatie scope
    - v10.12 no `DB::table('users')->where('role',...)` in `app/`
    - v10.13 `ReportsIcon` SVG + `vendor-nav-reports` testid + dashboard CTA testid
