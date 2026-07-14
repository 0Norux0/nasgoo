# Phase 10 v10.4 — Package Integrity Report

Per dev §M: forensic proof that the shipped archive contains the exact repaired files.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION file count: 1 (no nested duplicate project)
- Single artisan + single package.json at root
- VERSION content: `Phase 10 v10.4`

## Canonical fingerprint

**Aggregate SHA-256:** `14c5d36d3e01b4236411b668e4fc17645c906cb079ab04dc7d0cd37caf71bf35`

This value is computed as `sha256(concat("file:sha256\n" for each file))` where the file list is the 23 critical fix files below in this exact order. The `marketplace:fingerprint` command computes the same aggregate.

**Verification on the deployed source:**

```bash
php artisan marketplace:fingerprint --json | python3 -c "
import json, sys
d = json.load(sys.stdin)
print(\"Match:\", d[\"aggregate_sha256\"] == \"14c5d36d3e01b4236411b668e4fc17645c906cb079ab04dc7d0cd37caf71bf35\")"
```

**Match** → deployed source is provably Phase 10 v10.4. **Mismatch** → re-extract the archive.

## File-level SHA-256 (canonical, computed against `/home/claude/marketplace`)

| # | File | SHA-256 |
|---|---|---|
| 1 | `VERSION` | `7357663b106ab174dc99e962b4608e5f1f9c3d8a9db9646f3d44c794d06376e5` |
| 2 | `app/Models/Product.php` | `de2cc775f45cb5c3140af6fd97a6ab6a52c0e5c2a7816bc61cc396d45d015f9a` |
| 3 | `app/Filament/Resources/VendorResource.php` | `e95dd5c204159a946f49432b08609593ecab6f26377c20ddae526dba42ed5f52` |
| 4 | `app/Http/Controllers/Vendor/VendorProductController.php` | `6bce16301e09e540c5921a2325261db103f9c2b1b5dd6cf5cb3f92df378eb66a` |
| 5 | `app/Http/Controllers/Vendor/VendorOrderController.php` | `0aed6391cc1529c4638224a4c40b22830073e323078322222a590c5878dfa658` |
| 6 | `app/Http/Controllers/Admin/VendorFileController.php` | `72acbfb5e99f92ffa14e49e73c392bc21beecce28cac7a01f02586d1921da959` |
| 7 | `app/Http/Controllers/Admin/ReportsController.php` | `6b8b628deaf3fdcb4b550364b1584460c927c971b85b725f9aec9a5dc102f8d2` |
| 8 | `app/Http/Controllers/Vendor/VendorReportsController.php` | `d64f5554787d4dafd24cdb7f3f3524c9fa549691d968fdae3b4a228f75caec38` |
| 9 | `app/Http/Controllers/Public/SitemapController.php` | `67739b02e744102e5f2c47cad4698e0cddc262efb6d89c7786badb395f27b352` |
| 10 | `app/Domain/Vendor/VendorFileLinks.php` | `6e63350522aec6745472e10ce4fe61c0571c3e208bdcbb435064f09ea6c027c5` |
| 11 | `app/Providers/Filament/AdminPanelProvider.php` | `a18705cd850f9b1deac6566e1b044181cb81f304ff4d06612c62bb83e7f8b4aa` |
| 12 | `app/Http/Middleware/HandleInertiaRequests.php` | `c5ec3cbcd8bca20bd27f61b3f4cee8aae5aee3f640217d038d83b38c7115d7bc` |
| 13 | `app/Console/Commands/VerifyFixesCommand.php` | `9c5ff70a990fb95ed3730d620798194a250bdfce3a99634e82b2ef4471ed142f` |
| 14 | `app/Console/Commands/FingerprintCommand.php` | `d2daed0c98f4842c97043d2b0c2ec984e3dd3c7cf6ba98d84b21086ce12f28aa` |
| 15 | `resources/js/Layouts/AdminLayout.tsx` | `29f3e6e095f548d0d3b261c240ceee3786267a4504d6f558fbb841270262278e` |
| 16 | `resources/js/Layouts/VendorLayout.tsx` | `296433030f9cec07db24514a8eb7214d9daccfab1f420e2855464267f85043a3` |
| 17 | `resources/js/Layouts/StorefrontLayout.tsx` | `d15202e84751b6f865875e29210fcd6c1396ebdc18eb26314b8c991f615228fb` |
| 18 | `resources/js/Pages/Vendor/Orders/Show.tsx` | `f22ae6e3b5bfd447b60f16496915e3392cff28caca2cc53cb8ed73c896b0ed67` |
| 19 | `resources/js/Pages/Vendor/Orders/Index.tsx` | `2c88355d9929f24daa1ae6ac7526e4ec7c1f490f634c5875c271b4f897c2cca8` |
| 20 | `resources/css/app.css` | `4b179d7d4e8f2f6dd8d5173f00ae78d15584456301a3ada89748e0672a6de17d` |
| 21 | `routes/web.php` | `2d03e6eb0d7e9788c91621bcf550f4fa365d19e5fb87d8b24a59b619e9ffb478` |
| 22 | `scripts/deploy.sh` | `8759a78e11aa50bfb353984fecd8c269c8e8bfb720e6e30d5ce1d558c605ee99` |
| 23 | `.github/workflows/ci.yml` | `ad6c7812f63fc21221296eaf3de2753e82c4980c86f07c9c210b7555cd130ede` |

## Archive SHA-256

See sidecar files alongside the archive:

```
marketplace-phase-10-v10.4.tar.gz
marketplace-phase-10-v10.4.tar.gz.sha256
marketplace-phase-10-v10.4.zip
marketplace-phase-10-v10.4.zip.sha256
```

```bash
sha256sum -c marketplace-phase-10-v10.4.tar.gz.sha256
# Expected: marketplace-phase-10-v10.4.tar.gz: OK
```

## Leak check (post-build verification)

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules/`
- `marketplace/vendor/`
- `marketplace/.git/`
- `marketplace/tsconfig.verify.json`

## Old vulnerable code search (per §M step 5+6)

Searches performed against the extracted v10.4 archive (not the workspace) for the patterns the dev demanded:

| Pattern | Hits | Verdict |
|---|---|---|
| `Product::create($data)` followed by `images` in `$data` without prior `unset()` | 0 | ✓ safe |
| `$product->update($data)` with `images` in `$data` without prior `unset()` | 0 | ✓ safe |
| `->disableLabel(` in any `app/Filament/` file | 0 | ✓ Filament 3.x compatible |
| `Placeholder::make(` followed by `->disableLabel(` | 0 | ✓ no crash-inducing call |

## What changed in the v10.4 archive vs v10.3

Files modified by v10.4 (4 total):
- `VERSION` (Phase 10 v10.3 → Phase 10 v10.4)
- `.github/workflows/ci.yml` (4 new sub-checks + verdict line)
- (new) `app/Console/Commands/FingerprintCommand.php`
- (new) `tests/Feature/Phase10V104RegressionTest.php`

All v10.3 fix code is byte-identical to its v10.3 archive content (verified by SHA-256 comparison against the v10.3 archive on disk).
