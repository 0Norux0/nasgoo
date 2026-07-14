# Phase 11A — Package Integrity Report

Per dev §18.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11A`
- No nested duplicate project
- 0 PHP files modified vs Phase 10 final-approved
- 10 frontend/config files NEW or MODIFIED

## Archive SHA-256 — sidecar files

Verify before extracting:

```bash
sha256sum -c marketplace-phase-11A-ui-redesign.tar.gz.sha256
sha256sum -c marketplace-phase-11A-ui-redesign.zip.sha256
```

## File-level SHA-256 of v11A-touched files (workspace)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `6c76d969ade8ae0cdc631f3bc1d0bfb4036fd28fa729921e03e52d1e6bae68c2` |
| 2 | `tailwind.config.js` | `39617f8ed834ae1f74e310c2cca55f4813624b48816b0914246f8a72c689b19b` |
| 3 | `resources/css/app.css` | `53afb21c42eed7eadf93a61bb32624a9f3b87f224bd75fc1c5f97982563eb611` |
| 4 | `resources/js/Components/ui/v11/Button.tsx` | `153b5f9a82645c9f1e518883919b199fa636bddeb3c877042e9ae26583719ba2` |
| 5 | `resources/js/Components/ui/v11/primitives.tsx` | `24752f0b2cfb8661c6c95b01325a916512b76753a552c6f58f03195989b51bf5` |
| 6 | `resources/js/Components/ui/v11/ProductCard.tsx` | `11aab7c46ee7ac5ad686ce5e2b6da1802eaefb9d51edb902fd691db430a370ee` |
| 7 | `resources/js/Layouts/StorefrontLayout.tsx` | `cfaac7833764917d89cbb876c8a0c5e4bfab6ff112e50f0461876f2fd0341f13` |
| 8 | `resources/js/Pages/Welcome.tsx` | `33a5a7fc3929eee037b71d019d750391951e692a5353133efc9f2500e6d40419` |
| 9 | `tests/Feature/Phase11ARegressionTest.php` | `0dc63299b976a9d826e8acd90ba769ec285e0fd188d347b3abc3ea8c357098aa` |
| 10 | `.github/workflows/ci.yml` | `8c789e00917eab115c077071a713ce5059ab37001de27514c68f40d851eda5cc` |

## Leak check

The archive must NOT contain:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md` (planning doc, not for shipping)
- `marketplace/node_modules` (recreated by `npm ci`)
- `marketplace/vendor` (recreated by `composer install`)
- `marketplace/.git` (devops separate)
- `marketplace/tsconfig.verify.json` (sandbox-only)

## v10.0-v10.16 PHP file SHA-identity check

The following Phase 10 PHP files are UNTOUCHED in v11A. Their workspace SHA-256
matches the Phase 10 final-approved archive byte-for-byte:

- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/HomeController.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Models/User.php`
- `routes/web.php`
- `config/auth.php`, `config/session.php`, `config/cache.php`
- All migrations, all services, all Filament resources
- All app/Domain/* files

## §20 Extract-verify (performed against the shipped archive)

After build:
1. Extract into `/tmp/v11a/` — clean extraction
2. `VERSION` in archive = `Phase 11A`
3. All 10 v11A-touched files SHA-identical between workspace and archive
4. All Phase 10 PHP files SHA-identical (no accidental modifications)
5. CI YAML valid with 6 v11A sub-checks
6. Pest v11A test file present with 24 scenarios
7. No node_modules / vendor / .git / tsconfig.verify in archive
8. No nested duplicate project folder
9. All Phase 10 v10.0-v10.16 preservation markers intact in archive
