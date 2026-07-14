# Phase 10 v10.12 — Package Integrity Report

Per dev §14.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file
- VERSION content: `Phase 10 v10.12`
- No nested duplicate project
- Laravel ^11.0, Spatie Permission ^6.x (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.12.tar.gz
marketplace-phase-10-v10.12.tar.gz.sha256

marketplace-phase-10-v10.12.zip
marketplace-phase-10-v10.12.zip.sha256
```

Verify on the dev's host:
```bash
sha256sum -c marketplace-phase-10-v10.12.tar.gz.sha256
```

## File-level SHA-256 of v10.12-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `0497334029171bdd843713e9816134238476c09cd2cd26336b0af25d9f289820` |
| 2 | `app/Domain/Reports/ReportsService.php` | `be342e35ad8f03cb5a98bfd0087fad9bc20d7aaa00784d71e46681de91454623` |
| 3 | `tests/Feature/Phase10V1012RegressionTest.php` | `3a97d30bbca7955d1a0fdd3959b3a9209943f2a2458d7f656c59a2a1d5ca2ef7` |
| 4 | `.github/workflows/ci.yml` | `e3cdf21401aae532780a0dfb2b2e70a60113ef41ae896c3f2a96fdbd9daf5302` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md` (working notes; not for delivery)
- `marketplace/node_modules` (sandbox stubs only)
- `marketplace/vendor` (composer artifacts)
- `marketplace/.git` (sandbox git data)
- `marketplace/tsconfig.verify.json` (sandbox-only stub config)

## §14 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1012v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.12`
3. `ReportsService::marketplaceCounts` uses `User::role('customer')` Spatie scope in archive
4. `DB::table('users')->where('role',...)` regression pattern ABSENT from `app/` in archive
5. `User::where('role',...)` regression pattern ABSENT from `app/` in archive
6. `use App\Models\User;` import present in ReportsService in archive
7. v10.11 `SUM(requested_amount_minor)` preserved (2 sites — admin + per-vendor)
8. v10.10 `guardAdminReportsAccess` preserved in ReportsController (1 method def + 2 call sites)
9. v10.10 diagnostic + repair commands preserved in archive
10. v10.10 EnsureAdminReportsAccessSeeder preserved
11. v10.11 §3 `computeStatusOptions` preserved in VendorOrderController
12. v10.11 §4 Filament 5 defensive eager-loads preserved
13. v10.11 §2 getAllPermissions absence preserved
14. Pest test file `Phase10V1012RegressionTest.php` present (15 scenarios)
15. CI YAML valid + 2 new v10.12 sub-checks
16. SHA-256 of all v10.12-touched files match between workspace and archive
17. No node_modules / vendor / .git / tsconfig.verify in archive
18. No nested duplicate project folder
