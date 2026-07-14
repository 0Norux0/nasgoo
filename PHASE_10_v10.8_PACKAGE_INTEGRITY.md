# Phase 10 v10.8 — Package Integrity Report

Per dev §15.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file: ✓
- VERSION content: `Phase 10 v10.8`
- No nested duplicate project
- Laravel ^11.0, Filament ^3.2 (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.8.tar.gz
marketplace-phase-10-v10.8.tar.gz.sha256

marketplace-phase-10-v10.8.zip
marketplace-phase-10-v10.8.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.8.tar.gz.sha256
```

## File-level SHA-256 of v10.8-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `6dff3c778cd34fdd548711bd74f35657ce8fe457e4f95eefd88dd2a75a3f6f70` |
| 2 | `app/Domain/Pricing/PricingService.php` | `4aecf791758626cd386486cc3f2876e724f1cc2b53a9f529443aaddf1a238cca` |
| 3 | `database/migrations/2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns.php` | `350b0d51ebff2a993db53dee60d1ef2a8a1ed8082b43b0507d5b287a782edf18` |
| 4 | `app/Models/Order.php` | `fa9806c548e1c3263173bd267b56d1d3ce3c96bbc17bcb3b7f46d3302c3f0c2d` |
| 5 | `app/Models/OrderItem.php` | `2b3a65dc6e4f6d0029ef0010733f61e071398fd3a15672460641005a12104576` |
| 6 | `app/Http/Controllers/CatalogController.php` | `5112dcbffcfa07a372a498e1028f09f1451b45ecb66ef4ec23a19c749bc8e3d9` |
| 7 | `app/Http/Controllers/HomeController.php` | `660e1fa391d2e6b4b53bbab0bf4c4f38d77d0ea7ac5fef658d0529fc5d964d22` |
| 8 | `app/Http/Controllers/CartController.php` | `601328732fc57f9f0e397d9bcf73365c6c4f9dca4738953a3abc75bc51fea149` |
| 9 | `app/Http/Controllers/CheckoutController.php` | `87fae42dd9eb7e59f885a21caab5d8273f6ed2035945fc8110bfbd2762672102` |
| 10 | `app/Domain/Order/CheckoutService.php` | `49ed17e2aebe1bd7889cd284f914860af08fee9efd336a51906bfcb803a2402b` |
| 11 | `resources/js/Pages/Catalog/Index.tsx` | `85e2ab0d74dd8a976e421f7cb025897bbcc7e1ea6119fd3d338d4a8c7d6cf0fa` |
| 12 | `resources/js/Pages/Catalog/Show.tsx` | `7f53fd02fe33579ad2247ca8cfb7159e1571f3703fff9d4d019c5d906f66ebd0` |
| 13 | `resources/js/Pages/Welcome.tsx` | `57d334eb02b6c09cad4fa3ba87b3c4574ad900cf3f97af3ef630e102ac40e750` |
| 14 | `resources/js/Pages/Cart/Show.tsx` | `489467191782e58d905c4bea0934ccdf84c9a851aed4aff82ecfa37c0243e41d` |
| 15 | `tests/Feature/Phase10V108RegressionTest.php` | `6148f291c9ba1923a056c8556d4e6801852cbda047eddb761da03d8864d5b04c` |
| 16 | `.github/workflows/ci.yml` | `be6a2e7686ecb278ef35724c154056d9c72c3967f590971e6bcc071d295baf51` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules` (sandbox stubs only — not real packages)
- `marketplace/vendor` (composer artifacts)
- `marketplace/.git`
- `marketplace/tsconfig.verify.json` (sandbox-only stub config)

## §15 extract-verify

Performed against the shipped archive:

1. Extract into `/tmp/v108v` — clean extraction (no errors)
2. `VERSION` in archive = `Phase 10 v10.8`
3. `app/Domain/Pricing/PricingService.php` present
4. `database/migrations/2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns.php` present
5. Every pricing consumer (Catalog, Home, Cart, Checkout, CheckoutService) delegates to PricingService
6. React surfaces (Catalog/Index, Catalog/Show, Welcome, Cart/Show) render promotion badges + testids
7. Pest test file with 20 scenarios present
8. CI has 4 new v10.8 sub-checks
9. SHA-256 of all v10.8-touched files match between workspace and archive
10. No nested duplicate project folder
