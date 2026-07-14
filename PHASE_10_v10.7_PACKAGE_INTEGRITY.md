# Phase 10 v10.7 — Package Integrity Report

Per dev §13.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file: ✓
- VERSION content: `Phase 10 v10.7`
- No nested duplicate project
- Laravel ^11.0, Filament ^3.2 (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.7.tar.gz
marketplace-phase-10-v10.7.tar.gz.sha256

marketplace-phase-10-v10.7.zip
marketplace-phase-10-v10.7.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.7.tar.gz.sha256
```

## File-level SHA-256 of v10.7-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `97e3b813c0ef0e6569cd02cc603daee948b9ed58731c16ee093792d69adb0594` |
| 2 | `app/Domain/Vendor/VendorFileResolver.php` | `61175423872d269fa14e077b25839fdf0359d774d7c556c6e1cd4bcc4b09b99b` |
| 3 | `app/Domain/Vendor/VendorFileLinks.php` | `7a8cb3ba0dd1d56b9337102a4e0c85ff59e74bf8eec669c78c47de64ae178532` |
| 4 | `app/Http/Controllers/Admin/VendorFileController.php` | `ec63a22bf12f7cdf694a25c19a4be8bf2ff5cf8610a06845ed8361e146b8afac` |
| 5 | `app/Http/Controllers/Vendor/VendorRegistrationController.php` | `6d2962ba20da3ec9813a8c9254283959f1eb8bbb83cd6b7a47f3e2a5649eb511` |
| 6 | `config/marketplace.php` | `cdedc254c8f386e7032cf2e96bc59d2d61c1fb624da1bd1bfb18bce857cfc354` |
| 7 | `tests/Feature/Phase10V107RegressionTest.php` | `c0a8c2e19127caf8fec81a2918f9a3cd8210fac786fa8c5db538dc4840b4fae5` |
| 8 | `.github/workflows/ci.yml` | `530ff8f10055a93d40b557e98c41ee6d7bd47a98a2191dc5f4c0f801054704d8` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules` (sandbox stubs)
- `marketplace/vendor` (composer artifacts)
- `marketplace/.git`
- `marketplace/tsconfig.verify.json` (sandbox-only stub config)

## §13 extract-verify

Performed against the shipped archive:

1. Extract into `/tmp/v107v` — clean extraction (no errors)
2. `VERSION` in archive = `Phase 10 v10.7`
3. `app/Domain/Vendor/VendorFileResolver.php` present in archive
4. `app/Domain/Vendor/VendorFileLinks.php` in archive uses `VendorFileResolver::resolve`
5. `app/Http/Controllers/Admin/VendorFileController.php` in archive uses `VendorFileResolver::resolve` and includes `'logo'`/`'banner'` in ALLOWED_KINDS
6. `app/Http/Controllers/Vendor/VendorRegistrationController.php` in archive routes by kind
7. `config/marketplace.php` in archive contains `vendor_public_disk`
8. `tests/Feature/Phase10V107RegressionTest.php` in archive
9. SHA-256 of all v10.7-touched files match between workspace and archive
10. No nested duplicate project folder
