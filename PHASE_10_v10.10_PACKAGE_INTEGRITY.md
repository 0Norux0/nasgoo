# Phase 10 v10.10 — Package Integrity Report

Per dev §15.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file
- VERSION content: `Phase 10 v10.10`
- No nested duplicate project
- Laravel ^11.0, Filament ^3.2 (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.10.tar.gz
marketplace-phase-10-v10.10.tar.gz.sha256

marketplace-phase-10-v10.10.zip
marketplace-phase-10-v10.10.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.10.tar.gz.sha256
```

## File-level SHA-256 of v10.10-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `d80cd22963031248bc088b2a0532cf46e3bd623eff16d0a69338630419dc722b` |
| 2 | `app/Http/Controllers/Admin/ReportsController.php` | `0c8a676496fdc85e24755b20990cb3d9a8b8b3764c0c10d8533d66e0a3681fe1` |
| 3 | `app/Models/User.php` | `a87545e9496eb933c93dbf7b90cdbc784ce7b9dc4cae2317afc7ec8a840e8454` |
| 4 | `app/Console/Commands/DiagnoseReportsAccessCommand.php` | `71721cbd114a1487ccdd523cf306ae750c158b2437fae7c4bc2001a8c3ce6f0c` |
| 5 | `app/Console/Commands/RepairAdminReportsAccessCommand.php` | `494393ba1e59ed2777258652a34dc57035e5704cf2ff00543fac38cc9bf8cd96` |
| 6 | `database/seeders/EnsureAdminReportsAccessSeeder.php` | `fb302c91e84543247825fc90d9c506b5483b68595570b0089d6c8fee36bc5415` |
| 7 | `database/seeders/DatabaseSeeder.php` | `4f93f95df4212b990353a834b2ad527d8aefd8b9fbe1cf6be61be83bc0e0eaea` |
| 8 | `tests/Feature/Phase10V1010RegressionTest.php` | `0bcc6aeaa75608aad902d4eda0393a52d8badcb0c1456e8d7682acd7e5c33755` |
| 9 | `.github/workflows/ci.yml` | `261fabec597006f44a359362b98fc6719ef11defa0e461682f42eb336ab192fc` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md` (working notes; not for delivery)
- `marketplace/node_modules` (sandbox stubs only — the dev installs in their own env)
- `marketplace/vendor` (composer artifacts — the dev runs composer install)
- `marketplace/.git` (sandbox git data)
- `marketplace/tsconfig.verify.json` (sandbox-only stub config for tsc verification)

## §15 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1010v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.10`
3. `ReportsController::guardAdminReportsAccess()` method defined in archive
4. Both `index()` and `exportOrdersCsv()` call the guard (2 call sites)
5. Old policy-style `authorize('viewReports', User::class)` GONE from archive
6. `DiagnoseReportsAccessCommand` present in archive
7. `RepairAdminReportsAccessCommand` present in archive
8. `EnsureAdminReportsAccessSeeder` present in archive
9. `DatabaseSeeder` hooks `EnsureAdminReportsAccessSeeder` in archive
10. `User::canManageAdminReports()` broadened to accept admin/administrator + status gate removed in archive
11. Pest test file `Phase10V1010RegressionTest.php` present (18 scenarios)
12. CI YAML valid + 4 new v10.10 sub-checks
13. SHA-256 of all v10.10-touched files match between workspace and archive
14. No node_modules / vendor / .git / tsconfig.verify in archive
15. No nested duplicate project folder
