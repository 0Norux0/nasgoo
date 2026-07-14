# Phase 10 v10.6 — Critical Repair

## Three defects fixed (per dev §1-3)

### Defect 1 — `Disk [vendors] does not have a configured driver`

**Root cause:** v10.1's `app/Http/Controllers/Admin/VendorFileController.php:68` calls `Storage::disk(config('marketplace.vendor_private_disk', 'vendors'))`. The `'vendors'` disk was NEVER added to `config/filesystems.php`, so the call crashes with `InvalidArgumentException`. The config()'s fallback string `'vendors'` IS the missing disk, so the bug fires unconditionally.

**Fix:**
- `config/filesystems.php` — added `'vendors'` disk: driver=local, root=`storage_path('app/private')` (matches the default `local` disk's root, so `vendors/{id}/{filename}` paths resolve correctly without migration), visibility=private, throw=false.
- `config/marketplace.php` — added canonical `vendor_private_disk` key (default `'vendors'`, env-overridable via `VENDOR_PRIVATE_DISK`).

The bug was deterministic — every admin click on a pending vendor crashed.

### Defect 2 — Vendor order-status dropdown still cannot apply changes

**Root cause:** v10.3's dropdown's onChange handler called native browser `confirm()` dialogs before submitting. If the user accidentally dismisses the dialog (Cancel, Esc, misclick), the dropdown silently resets to "Current: …" with zero feedback. From the user's POV: "the dropdown does nothing."

**Fix:** `resources/js/Pages/Vendor/Orders/Show.tsx` — removed `confirm()` from the dropdown path. New `submitStatusChange(action)` function calls `router.post(url, {}, {preserveScroll:true, onFinish: ...})` directly. Inline `statusSubmitting` state shows "Updating…" while in flight. The dropdown is disabled visually + functionally during submission. Inline buttons preserve their separate confirm() dialogs (different UX — clicking a button + confirming is intentional; selecting from a dropdown is more direct).

### Defect 3 — Mobile categories outside hamburger

**Root cause:** `resources/js/Pages/Catalog/Index.tsx` renders a categories `<aside>` that, at mobile widths (1-column grid), appears ABOVE the products grid — outside the hamburger drawer. The dev saw "3 categories outside hamburger" because the first 3 categories fit above the fold.

**Fix (multi-layer):**
1. `app/Http/Middleware/HandleInertiaRequests.php` — added `top_categories` shared Inertia prop (1h cache). All React components can now access the category list globally.
2. `resources/js/Layouts/StorefrontLayout.tsx` — added a collapsible Categories section to the mobile drawer. Default collapsed; tapping the header toggles via `categoriesOpen` state; chevron rotates 90° when open. Selecting any category navigates AND closes the entire drawer.
3. `resources/js/Pages/Catalog/Index.tsx` — the standalone `<aside>` is now `hidden lg:block` (desktop-only).
4. `resources/js/types/inertia.d.ts` — `top_categories: Array<{slug,name}>` added to SharedProps.

## Files modified — exhaustive

| File | Change |
|---|---|
| `config/filesystems.php` | Added `'vendors'` disk (Defect 1) |
| `config/marketplace.php` | Added `vendor_private_disk` key (Defect 1) |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared `top_categories` (Defect 3) |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | Dropdown handler rewritten without confirm() (Defect 2) |
| `resources/js/Layouts/StorefrontLayout.tsx` | Collapsible Categories in mobile drawer (Defect 3) |
| `resources/js/Pages/Catalog/Index.tsx` | aside hidden on mobile (Defect 3) |
| `resources/js/types/inertia.d.ts` | top_categories on SharedProps (Defect 3) |
| `VERSION` | v10.5 → v10.6 |
| `.github/workflows/ci.yml` | 3 new sub-checks + v10.6 Pest runner + verdict |
| `tests/Feature/Phase10V106RegressionTest.php` | NEW — 11 Pest scenarios |

## What's intentionally NOT in v10.6

- No new features
- No new optional phases
- No documentation expansions beyond the dev's §8 deliverables
- No changes to v1-v9 code
- No changes to v10.0-v10.5 fix code (they're preserved verbatim)

## Counts

| | v10.5 → v10.6 |
|---|---|
| Phase 10 CI sub-checks | 30 → 34 (6+7+5+5+4+3+4) |
| Phase 10 Pest scenarios | 55 → 66 (13+14+8+8+6+6+11) |
| Phase-specific CI grand total | 85 → 89 |
| Unique global helpers | 51 (unchanged — v10.6 tests use `it()` only) |
| Files modified | 6 source + 1 CI + 1 test + VERSION |
| v1-v9 files touched | 0 |
| v10.0-v10.5 fix code reverted | 0 |

## Static verification

```
$ /home/claude/.npm-global/bin/tsc -p tsconfig.verify.json
$ echo $?
0
```

Real TypeScript compiler against all 9 v10.x-touched React files using v7.7-style stubs.

## Per §O acceptance

Per dev §O final clause: "If runtime testing is unavailable, say 'Phase 10 v10.6 is implemented but requires developer runtime verification.' Do not say it passes."

**Phase 10 v10.6 is implemented but requires developer runtime verification.**

My sandbox cannot execute the dev's mandatory commands (composer install / npm ci / npm run build / php artisan migrate:fresh --seed / php artisan test). My evidence is structural (SHA-256, tsc with stubs, grep of fix markers). The dev's environment is the authoritative source.
