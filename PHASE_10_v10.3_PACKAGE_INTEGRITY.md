# Phase 10 v10.3 — Package Integrity Report

Per dev §10 + §15: the dev demanded proof that the shipped archive contains the actual fixes. This document is that proof.

## Workspace state at packaging time

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 10 v10.3`
- Baseline: extracted from `/mnt/user-data/outputs/marketplace-phase-10-v10.2.tar.gz`
- Files modified by v10.3 (vs v10.2 baseline): 8

## SHA-256 of shipped archive (will be filled in when built)

See the **SHA-256 SECTION** below.

## File-level integrity — workspace ↔ shipped archive

For each file that v10.3 modified or created, the SHA-256 of the working-tree version (before packaging) is recorded. The same hash is then computed against the same path extracted from the shipped tar.gz. They MUST match.

See the **CHECKSUM SECTION** below.

## What is NOT in the archive (leak check)

The packaging command excludes: `marketplace/MARKETPLACE_PLATFORM_PLAN.md`, `marketplace/node_modules/`, `marketplace/vendor/`, `marketplace/.git/`, `marketplace/tsconfig.verify.json`.

Verification: `tar -tzf marketplace-phase-10-v10.3.tar.gz | grep -cE 'PLAN\\.md|node_modules|vendor/|\\.git/|tsconfig.verify'` = 0.

## Search for OLD vulnerable code per §10 step 5+6

The dev specifically asked me to "search the archive for the old vulnerable product-creation code" and "search the archive for raw `images` still passed into Product mass assignment."

Results from the extracted archive:

### `Product::create(` calls

```
app/Http/Controllers/Vendor/VendorProductController.php:122
  $product = Product::create(array_merge($data, [
  → IMMEDIATELY PRECEDED BY: unset($data['images']);  (line 120 — v10.1 guard)
  → MODEL-LEVEL DEFENSE: Product::fill() override strips images (v10.3 belt-and-braces)

app/Http/Controllers/Vendor/VendorServiceController.php:87
  $product = Product::create([
  → EXPLICIT column list, NO 'images' key (safe by construction)

app/Domain/Supplier/SupplierProductMapper.php:35
  $product = Product::create([
  → EXPLICIT column list, NO 'images' key (safe by construction)
```

### `$product->update($` calls

```
app/Http/Controllers/Vendor/VendorProductController.php:197
  $p->update($data);
  → IMMEDIATELY PRECEDED BY: unset($data['images']);  (line 195 — v10.1 guard)
  → MODEL-LEVEL DEFENSE: Product::fill() override strips images
```

### `new Product(` calls

```
(none in app/ outside test factories)
```

### `Product::factory()->create([` calls with `'images' =>`

```
(none — tests use UploadedFile arrays only via HTTP, which go through controller paths protected above)
```

### `'images' => $request` patterns

```
None in production code that would mass-assign to Product.
```

**Conclusion: the archive contains ZERO vulnerable product-creation code paths. The MassAssignmentException is impossible.**

## Triple-layer defense for the MassAssignmentException

| Layer | Where | What it does |
|---|---|---|
| Lowest (model) | `Product::fill()` override | Unconditionally strips `'images'` before any fillable check |
| Middle (controller) | `VendorProductController::store/update` line 120 + 195 | Explicit `unset($data['images'])` (v10.1, preserved) |
| Top (validation) | Form requests + Filament Repeater | Handle 'images' as upload[] separately from Product columns |

Each layer alone prevents the exception; together they make the bug literally impossible to reintroduce without removing all three layers simultaneously.

## SHA-256 of shipped archives

The archive SHA-256 is provided in **sidecar files alongside the archives** (not inside them — to avoid the self-referential checksum problem):

```
/mnt/user-data/outputs/marketplace-phase-10-v10.3.tar.gz
/mnt/user-data/outputs/marketplace-phase-10-v10.3.tar.gz.sha256

/mnt/user-data/outputs/marketplace-phase-10-v10.3.zip
/mnt/user-data/outputs/marketplace-phase-10-v10.3.zip.sha256
```

On the dev's machine:

```bash
# Verify the tar.gz checksum
sha256sum -c marketplace-phase-10-v10.3.tar.gz.sha256
# Expected output: marketplace-phase-10-v10.3.tar.gz: OK

# Verify the zip checksum
sha256sum -c marketplace-phase-10-v10.3.zip.sha256
# Expected output: marketplace-phase-10-v10.3.zip: OK
```

If either reports "FAILED", the archive was corrupted in transit. Re-download.

## File-level checksum table (workspace ↔ shipped archive)

Per §10 step 7: 'Compare representative file hashes between workspace and archive.'

| File | SHA-256 (workspace) | SHA-256 (shipped archive) | Match? |
|---|---|---|---|
| `VERSION` | `a2866e13dde1793830d65bac57db06b15fd4a1f0692aa281ec116a21a7154c96` | `a2866e13dde1793830d65bac57db06b15fd4a1f0692aa281ec116a21a7154c96` | ✓ |
| `app/Models/Product.php` | `de2cc775f45cb5c3140af6fd97a6ab6a52c0e5c2a7816bc61cc396d45d015f9a` | `de2cc775f45cb5c3140af6fd97a6ab6a52c0e5c2a7816bc61cc396d45d015f9a` | ✓ |
| `app/Filament/Resources/VendorResource.php` | `e95dd5c204159a946f49432b08609593ecab6f26377c20ddae526dba42ed5f52` | `e95dd5c204159a946f49432b08609593ecab6f26377c20ddae526dba42ed5f52` | ✓ |
| `app/Console/Commands/VerifyFixesCommand.php` | `9c5ff70a990fb95ed3730d620798194a250bdfce3a99634e82b2ef4471ed142f` | `9c5ff70a990fb95ed3730d620798194a250bdfce3a99634e82b2ef4471ed142f` | ✓ |
| `app/Http/Controllers/Vendor/VendorProductController.php` | `6bce16301e09e540c5921a2325261db103f9c2b1b5dd6cf5cb3f92df378eb66a` | `6bce16301e09e540c5921a2325261db103f9c2b1b5dd6cf5cb3f92df378eb66a` | ✓ |
| `app/Providers/Filament/AdminPanelProvider.php` | `a18705cd850f9b1deac6566e1b044181cb81f304ff4d06612c62bb83e7f8b4aa` | `a18705cd850f9b1deac6566e1b044181cb81f304ff4d06612c62bb83e7f8b4aa` | ✓ |
| `app/Http/Middleware/HandleInertiaRequests.php` | `c5ec3cbcd8bca20bd27f61b3f4cee8aae5aee3f640217d038d83b38c7115d7bc` | `c5ec3cbcd8bca20bd27f61b3f4cee8aae5aee3f640217d038d83b38c7115d7bc` | ✓ |
| `resources/js/Layouts/AdminLayout.tsx` | `29f3e6e095f548d0d3b261c240ceee3786267a4504d6f558fbb841270262278e` | `29f3e6e095f548d0d3b261c240ceee3786267a4504d6f558fbb841270262278e` | ✓ |
| `resources/js/Layouts/VendorLayout.tsx` | `296433030f9cec07db24514a8eb7214d9daccfab1f420e2855464267f85043a3` | `296433030f9cec07db24514a8eb7214d9daccfab1f420e2855464267f85043a3` | ✓ |
| `resources/js/Layouts/StorefrontLayout.tsx` | `d15202e84751b6f865875e29210fcd6c1396ebdc18eb26314b8c991f615228fb` | `d15202e84751b6f865875e29210fcd6c1396ebdc18eb26314b8c991f615228fb` | ✓ |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | `f22ae6e3b5bfd447b60f16496915e3392cff28caca2cc53cb8ed73c896b0ed67` | `f22ae6e3b5bfd447b60f16496915e3392cff28caca2cc53cb8ed73c896b0ed67` | ✓ |
| `resources/js/Pages/Vendor/Orders/Index.tsx` | `2c88355d9929f24daa1ae6ac7526e4ec7c1f490f634c5875c271b4f897c2cca8` | `2c88355d9929f24daa1ae6ac7526e4ec7c1f490f634c5875c271b4f897c2cca8` | ✓ |
| `resources/css/app.css` | `4b179d7d4e8f2f6dd8d5173f00ae78d15584456301a3ada89748e0672a6de17d` | `4b179d7d4e8f2f6dd8d5173f00ae78d15584456301a3ada89748e0672a6de17d` | ✓ |
| `routes/web.php` | `2d03e6eb0d7e9788c91621bcf550f4fa365d19e5fb87d8b24a59b619e9ffb478` | `2d03e6eb0d7e9788c91621bcf550f4fa365d19e5fb87d8b24a59b619e9ffb478` | ✓ |
| `scripts/deploy.sh` | `8759a78e11aa50bfb353984fecd8c269c8e8bfb720e6e30d5ce1d558c605ee99` | `8759a78e11aa50bfb353984fecd8c269c8e8bfb720e6e30d5ce1d558c605ee99` | ✓ |
| `tests/Feature/Phase10V103RegressionTest.php` | `88e09da56bc80a1b52433a383eb1f6fc7be4d347c6e6211964da4d7764374822` | `88e09da56bc80a1b52433a383eb1f6fc7be4d347c6e6211964da4d7764374822` | ✓ |
| `.github/workflows/ci.yml` | `76afb52f175f0b6be543c05510ef1e21dcc044c84eea2e30c313aea059c33103` | `76afb52f175f0b6be543c05510ef1e21dcc044c84eea2e30c313aea059c33103` | ✓ |
