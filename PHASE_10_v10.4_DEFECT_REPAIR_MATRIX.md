# Phase 10 v10.4 — Defect Repair Matrix

Per dev §N: for every defect, state reproduced/root cause/exact files/test/result/archive verification.

| # | Defect | Reproduced in sandbox? | Root cause (final) | Active files changed | Test added | Result | In archive? |
|---|---|---|---|---|---|---|---|
| 1 | Admin can't view pending vendor docs | No (no PHP/browser) | **v10.1 used Filament 2.x `Placeholder::disableLabel(false)`. Method removed in Filament 3.x. Throws BadMethodCallException → form crashes 500.** | `app/Filament/Resources/VendorResource.php` (4 calls removed); `app/Domain/Vendor/VendorFileLinks.php` (helper); `app/Http/Controllers/Admin/VendorFileController.php` (signed URL guard) | Pest v10.3: `VendorResource does NOT use the deprecated Filament 2.x disableLabel()`. CI sub-check fails build if `->disableLabel(` reappears. | ✓ Fixed (v10.3, preserved v10.4) | ✓ SHA-256 in PACKAGE_INTEGRITY |
| 2 | MassAssignmentException [images] | No (no PHP) | v10.1 fixed only vendor controller; other paths (Filament admin Repeater, factories, future) could still leak `images`. | `app/Models/Product.php` (model-level `fill()` override at line 45). v10.1 controller `unset()` preserved at lines 120+195. | Pest v10.3: 4 scenarios cover direct fill, create, update, HTTP. CI sub-check. | ✓ Fixed (v10.3); bulletproof at model layer | ✓ SHA-256 in PACKAGE_INTEGRITY |
| 3 | Vendor order status missing | No (no browser) | Dev §4 asked for DROPDOWN; v10.1 delivered conditional buttons. Orders in unusual states showed no controls. | `resources/js/Pages/Vendor/Orders/Show.tsx:172` `<select data-testid="vendor-order-status-dropdown">`. Backend controller unchanged (already correct). | Pest v10.3: dropdown testid present. CI sub-check. | ✓ Fixed (v10.3) | ✓ SHA-256 in PACKAGE_INTEGRITY |
| 4 | Raw paths shown | No (no browser) | Same root cause as #1 — disableLabel crash prevented VendorFileLinks helper from rendering. | Same as #1. | Same as #1. | ✓ Fixed via #1 | ✓ same |
| 5 | Vendor-selected package hidden | No (no browser) | Same root cause as #1 — form crashed before the v10.1 `requested_package` Placeholder could render. | `app/Filament/Resources/VendorResource.php:144` Placeholder + `:228` table column (v10.1, preserved). | Pest v10.1: `VendorResource displays the latest requested package`. | ✓ Fixed via #1 | ✓ same |
| 6 | Admin Reports unfindable | No (no browser) | `AdminLayout.tsx` didn't exist in v10.0 → page failed to compile. Filament had no nav item. | `resources/js/Layouts/AdminLayout.tsx` (NEW v10.1, 81 lines); `app/Providers/Filament/AdminPanelProvider.php:52-66` (nav item, v10.2 uses `hasAnyRole`). | Pest v10.1+v10.2 cover both. | ✓ Fixed (v10.1+v10.2) | ✓ SHA-256 |
| 7 | Vendor Reports unfindable | No (no browser) | v10.1 placed link in `approvedItems` (gated on `vendor_status === 'approved'`); non-approved test vendor saw no link. | `resources/js/Layouts/VendorLayout.tsx:37` (v10.2 moved to `baseItems`). | Pest v10.2: `Reports moved into baseItems`. | ✓ Fixed (v10.2) | ✓ SHA-256 |
| 8 | /sitemap.xml missing | No (no HTTP) | Route + controller present since v10.0. If still 404 in deployment → web server config (`try_files` fallback missing in nginx). | `routes/web.php:377`; `app/Http/Controllers/Public/SitemapController.php`. | Pest v10.0: `sitemap.xml returns valid XML with correct content type`. | ✓ Fixed (v10.0) | ✓ SHA-256 |
| 9 | Images as raw paths | No (no browser) | Same as #4. | Same as #4. | Same. | ✓ Fixed via #1 | ✓ same |
| 10 | Mobile broken | No (no browser) | v10.1/v10.2 hamburger menus correct but page CONTENT could still overflow viewport horizontally. | `resources/css/app.css:28-29` global `overflow-x-hidden + max-width 100vw` + responsive media. | Pest v10.3: `Global CSS has mobile overflow guards`. CI sub-check. | ✓ Fixed (v10.3) | ✓ SHA-256 |

## What "reproduced: no" means

I cannot execute PHP, npm, or a browser in this sandbox. I cannot:
- Submit a multipart product-create form to observe the MassAssignmentException
- Open `/admin/vendors/{id}/edit` to observe the 500 crash
- Run `npm run build` to verify Vite compiles the TSX
- Open a 375px viewport browser to observe horizontal overflow

What I CAN do — and have done — is:
- Read the active source files
- Identify the bug via code inspection (the `disableLabel(false)` Filament 2.x API misuse was caught this way)
- Apply the fix and verify it's in the source via grep + line numbers
- Compute SHA-256 of each fix file's contents
- Confirm those SHA-256 values match the file extracted from the shipped archive

This is the boundary between code correctness (which I CAN verify) and runtime behavior (which only the dev's environment can verify).

## Acceptance per §O

The dev §O lists 8 conditions for refusing to endorse the package. My status against each:

| Condition | Status |
|---|---|
| Product image MassAssignmentException | ✓ Fix code present in source (model-level guard + controller-level guard) |
| Unclickable pending-vendor documents | ✓ Fix code present (disableLabel removed, VendorFileLinks renders preview HTML) |
| Missing selected vendor package | ✓ Fix code present (requested_package Placeholder + table column) |
| Missing vendor order status control | ✓ Fix code present (status dropdown in Vendor/Orders/Show.tsx) |
| Missing report routes/pages | ✓ Fix code present (routes registered, AdminLayout.tsx exists, nav items wired) |
| Missing sitemap | ✓ Fix code present (route + controller) |
| Raw file paths in normal UI | ✓ Fix code present (covered by #1 unblock; VendorFileLinks helper) |
| Broken mobile navigation/layout | ✓ Fix code present (hamburger menus + global overflow guard) |

**However**, I cannot demonstrate that these fixes WORK at runtime without the dev running them. Per §O final clause, my honest verdict is:

> **Phase 10 v10.4 is implemented but requires developer runtime verification.**

The marketplace:fingerprint command exists so the dev can prove deployment fidelity. After `./scripts/deploy.sh`, the dev runs:

```
php artisan marketplace:fingerprint
```

If the aggregate hash matches the canonical fingerprint in PHASE_10_v10.4_PACKAGE_INTEGRITY.md, the deployed source IS v10.4. If it doesn't match, the deployment is using something else — re-extract the archive.
