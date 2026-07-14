# Phase 10 v10.6 — Defect Repair Matrix

Workspace forensics:
- Project root: `/home/claude/marketplace`
- Single VERSION file (no nested duplicate): ✓
- Baseline: extracted from `marketplace-phase-10-v10.5.tar.gz`
- VERSION at packaging: `Phase 10 v10.6`
- Laravel `^11.0` + Filament `^3.2` + Inertia `^2.0` + React `^18.3` (composer.json + package.json)

| # | Defect | Reproduced | Root cause | Active files changed | Test performed | Runtime result |
|---|---|---|---|---|---|---|
| 1 | Pending vendor application crashes — `Disk [vendors] has no configured driver` | No (no PHP runtime in sandbox); confirmed by code inspection — `Storage::disk('vendors')` called from `app/Http/Controllers/Admin/VendorFileController.php:68` against `config('marketplace.vendor_private_disk', 'vendors')`; the `'vendors'` key did NOT exist in `config/filesystems.php` disks array | v10.1 introduced a controller that read from `Storage::disk('vendors')` but no `vendors` disk was ever added to `config/filesystems.php`. Default fallback in the config() call was `'vendors'` (the missing disk's own name), so the crash was unconditional | `config/filesystems.php` — added `'vendors'` disk entry (driver=local, root=storage/app/private, visibility=private, throw=false). Root matches where `VendorRegistrationController` stores uploads (paths like `vendors/{id}/{filename}` on the default `local` disk, root `storage/app/private`); the new `vendors` disk root must equal storage/app/private — NOT storage/app/private/vendors — to avoid path double-nesting.  `config/marketplace.php` — added canonical `vendor_private_disk` key (default `'vendors'`, override via `VENDOR_PRIVATE_DISK` env) | Pest: `'vendors' disk is configured with a driver`; `config('marketplace.vendor_private_disk') resolves to a configured disk`; `Storage::disk(...) does not throw`; `vendors disk root resolves under storage/app/private`. CI sub-check `Phase 10 v10.6 — 'vendors' disk configured in filesystems.php` greps for the disk entry + the correct root | ✓ Fixed at source. Effective immediately after `php artisan config:cache` rebuild. |
| 2 | Vendor order-status dropdown cannot apply status changes | No (no browser in sandbox); confirmed by code reading — v10.3 dropdown handlers called native `confirm()` dialogs before submission. Any accidental Cancel/Esc on the dialog silently resets the dropdown with zero feedback → user perceives "the dropdown doesn't work" | The v10.3 onStatusChange handler called confirmOrder/ship/deliver, each of which prompts `if (!confirm('...')) return;`. If the user dismisses, the dropdown's `e.target.value = '__current'` line still runs, the page never reloads, and there's no error message — the user sees the dropdown "snap back" with no effect | `resources/js/Pages/Vendor/Orders/Show.tsx` — replaced the dropdown's onStatusChange path. No more confirm() inside the dropdown flow. New `submitStatusChange(action)` calls `router.post(url, {}, {preserveScroll:true, onFinish: ...})` directly. Inline `statusSubmitting` state shows an "Updating…" indicator (data-testid='vendor-order-status-submitting'). Dropdown is disabled (visually + functionally) while a submission is in flight. Inline buttons preserve their confirm() dialogs separately. The 3 existing backend routes (vendor.orders.confirm/ship/deliver) are unchanged | Pest: `Vendor/Orders/Show.tsx no longer uses confirm() in the dropdown handler`; `exposes Updating… loading indicator`. CI sub-check `Phase 10 v10.6 — vendor order status dropdown submits without confirm() trap` greps for submitStatusChange + statusSubmitting + the indicator testid | ✓ Fixed in source. Requires `npm run build` to compile. |
| 3 | Mobile: 3 categories visible OUTSIDE the hamburger menu (should be ALL categories INSIDE hamburger in a collapsible section) | No (no browser); confirmed by code reading — `resources/js/Pages/Catalog/Index.tsx` rendered a `<aside>` with Categories. On `/products` at mobile widths, the grid collapses to 1 column (grid-cols-1), and the aside appears ABOVE the products grid — outside the hamburger drawer. The first 3 categories appear above the fold; subsequent ones below | Catalog/Index sidebar was always rendered (no responsive hiding). The storefront hamburger drawer didn't include categories at all (couldn't — categories weren't shared via Inertia globally). Categories were only available as a page prop on the catalog index | (a) `app/Http/Middleware/HandleInertiaRequests.php` — added `top_categories` shared prop (slug+name, ordered by `position`, active only, top 50, cached 1 hour at key `marketplace:top_categories:v1`). (b) `resources/js/Layouts/StorefrontLayout.tsx` — added collapsible "Categories" section as FIRST item in mobile drawer. Toggle button has `data-testid='storefront-mobile-categories-toggle'`; list has `data-testid='storefront-mobile-categories-list'` and only renders when `categoriesOpen` state is true. Tapping any category navigates AND closes both `mobileOpen` and `categoriesOpen`. Chevron rotates 90° when expanded. (c) `resources/js/Pages/Catalog/Index.tsx` — `<aside>` is now `hidden lg:block` (desktop-only). Mobile users see categories ONLY inside the hamburger drawer. (d) `resources/js/types/inertia.d.ts` — `top_categories: Array<{slug,name}>` added to `SharedProps` | Pest: `StorefrontLayout has a collapsible Categories toggle in mobile drawer`; `Catalog/Index <aside> is hidden on mobile`; `HandleInertiaRequests shares top_categories`; `top_categories shared prop returns an array of {slug,name} pairs`. CI sub-check `Phase 10 v10.6 — mobile categories live INSIDE the hamburger` enforces all 4 markers | ✓ Fixed in source. Requires `npm run build` + page reload to be visible. |

## Active code chains (per defect)

### Defect 1
```
Browser → Filament admin: /admin/vendors/{id}/edit
    → app/Filament/Resources/VendorResource.php (Documents Placeholders)
    → app/Domain/Vendor/VendorFileLinks.php::previewHtml
    → URL::temporarySignedRoute('admin.vendor-files.show', ...)
Browser → /admin/vendor-files/{vendor}/{kind}
    → routes/web.php:370 (admin.vendor-files.show)
    → app/Http/Controllers/Admin/VendorFileController::show
    → Storage::disk(config('marketplace.vendor_private_disk')) ← v10.6 disk config
    → Storage::disk(...)->response($path)
```

### Defect 2
```
Browser → vendor selects dropdown option in /vendor/orders/{id}
    → resources/js/Pages/Vendor/Orders/Show.tsx::onStatusChange
    → submitStatusChange('ship'|'confirm'|'deliver')         ← v10.6 (no confirm())
    → Inertia router.post('/vendor/orders/{id}/<action>')
    → routes/web.php:93-96 (vendor.orders.{ship,confirm,deliver})
    → app/Http/Controllers/Vendor/VendorOrderController::{ship,confirm,deliver}
    → app/Domain/Order/OrderLifecycleService::{markShipped,confirm,markDelivered}
    → DB transaction: update order_items, recalc parent Order status
    → back()->with('success', ...) → Inertia reloads with flash
```

### Defect 3
```
Inertia middleware shares 'top_categories' on EVERY request (cached 1h)
    → app/Http/Middleware/HandleInertiaRequests.php (v10.6)
    → resources/js/types/inertia.d.ts SharedProps.top_categories

Browser at 375px → /products
    → resources/js/Pages/Catalog/Index.tsx <aside hidden lg:block> ← v10.6 hides on mobile
    → user opens hamburger
    → resources/js/Layouts/StorefrontLayout.tsx Mobile drawer:
        → "Categories" button toggles categoriesOpen
        → list of top_categories renders when categoriesOpen=true
        → Link href="/products?category={slug}" navigates AND closes drawer
```

## SHA-256 of files changed in v10.6

Computed against `/home/claude/marketplace/` at packaging time. The full table is in `PHASE_10_v10.6_PACKAGE_INTEGRITY.md`.

| File | Modified by v10.6? | Verified in shipped archive? |
|---|---|---|
| `config/filesystems.php` | yes (added vendors disk) | (see PACKAGE_INTEGRITY) |
| `config/marketplace.php` | yes (added vendor_private_disk key) | (see PACKAGE_INTEGRITY) |
| `app/Http/Middleware/HandleInertiaRequests.php` | yes (added top_categories share) | (see PACKAGE_INTEGRITY) |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | yes (dropdown handler rewritten) | (see PACKAGE_INTEGRITY) |
| `resources/js/Layouts/StorefrontLayout.tsx` | yes (collapsible Categories in mobile drawer) | (see PACKAGE_INTEGRITY) |
| `resources/js/Pages/Catalog/Index.tsx` | yes (aside hidden on mobile) | (see PACKAGE_INTEGRITY) |
| `resources/js/types/inertia.d.ts` | yes (top_categories on SharedProps) | (see PACKAGE_INTEGRITY) |
| `tests/Feature/Phase10V106RegressionTest.php` | yes (NEW, 11 scenarios) | (see PACKAGE_INTEGRITY) |
| `.github/workflows/ci.yml` | yes (3 sub-checks + Pest runner + verdict) | (see PACKAGE_INTEGRITY) |
| `VERSION` | yes (v10.5 → v10.6) | (see PACKAGE_INTEGRITY) |

## Acceptance

Per the dev's §1: "The result may be marked '✅ Phase 10 v10.6 PASSES' only after both commands have actually completed successfully." I have not run the dev's exact `composer install / npm ci / php artisan optimize:clear / migrate:status / route:list / test / npm typecheck / npm build` chain in this sandbox (no PHP runtime, no network). What I HAVE done:
- Ran real `tsc` against all 9 v10.x-touched React files using v7.7-style stubs → exit 0
- SHA-256 verified that archive contents = workspace contents
- The CI Frontend job (`.github/workflows/ci.yml:4318-4325`) runs the real `npm run typecheck && npm run build` against the dev's pinned packages

Per §8 final clause: this package is implemented but **requires developer runtime verification** before being marked PASSED.
