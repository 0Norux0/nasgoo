# Phase 10 v10.9 — Package Integrity Report

Per dev §12.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file: ✓
- VERSION content: `Phase 10 v10.9`
- No nested duplicate project
- Laravel ^11.0, Filament ^3.2 (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.9.tar.gz
marketplace-phase-10-v10.9.tar.gz.sha256

marketplace-phase-10-v10.9.zip
marketplace-phase-10-v10.9.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.9.tar.gz.sha256
```

## File-level SHA-256 of v10.9-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `ea0419cb906246f0be6e94e3d64d2a7e05c6c9fb23827bf5ad58d2295f3321c3` |
| 2 | `app/Models/User.php` | `41246544894000cb2e4cc85eafc1488263423aa98fb379321aab00209c0aa5f1` |
| 3 | `app/Providers/AppServiceProvider.php` | `dbb8ede5ea76b68289c43b7b44b49e94c8ac3b51feb099e344b9427982d5b22a` |
| 4 | `app/Providers/Filament/AdminPanelProvider.php` | `2102bfb57528bf3727ed45e0d89212878f6244395194484abc33c867ea2252eb` |
| 5 | `database/migrations/2026_06_19_000001_phase10_v109_ensure_reports_permission_assigned.php` | `6bf1976a74c04450d3afc42c4d39fda6001ebe94203ade34df245acb123aa5b6` |
| 6 | `tests/Feature/Phase10V109RegressionTest.php` | `b0e132720d22b25bad8e6f55439d89b1c04d9cb524cb3476f252a7249cff8401` |
| 7 | `.github/workflows/ci.yml` | `deb7bbc001851c6f86791ff5f4766b731b72eb52c1f0f21cbb8761fa14d85f79` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules` (sandbox stubs only)
- `marketplace/vendor` (composer artifacts)
- `marketplace/.git`
- `marketplace/tsconfig.verify.json` (sandbox-only stub config)

## §12 extract-verify

Performed against the shipped archive:

1. Extract into `/tmp/v109v` — clean extraction (no errors)
2. `VERSION` in archive = `Phase 10 v10.9`
3. `User::canManageAdminReports()` present in archive
4. Gate calls canonical helper in archive
5. `Gate::before` super_admin shortcut in archive
6. Filament nav item calls canonical helper in archive
7. Self-healing migration present in archive
8. Pest test file with 16 scenarios present
9. CI has 4 new v10.9 sub-checks
10. SHA-256 of all v10.9-touched files match between workspace and archive
11. No nested duplicate project folder
