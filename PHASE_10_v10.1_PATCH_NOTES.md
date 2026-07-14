# Phase 10 v10.1 — Correction Package

**Status:** correction release responding to the developer's manual-test report. **Phase 10 v10.0 IS NOT launch-ready** — the developer's testing surfaced multiple confirmed defects, the most severe being that vendor product creation crashed and the admin reports page literally couldn't render.

This release does not claim launch-readiness. It fixes the developer-confirmed defects and adds CI guards so the same bugs cannot regress.

---

## What the developer reported vs what was found

| Reported issue | Status | Root cause |
|---|---|---|
| 1. Whole site feels slow and laggy | **Partially addressed** | No single hot query — addressed by caching translations + adding 6 composite DB indexes targeting hot WHERE clauses. Full performance audit findings in `PHASE_10_v10.1_PERFORMANCE_FINDINGS.md`. |
| 2. Admin can't view vendor images | **FIXED** | Filament `VendorResource` displayed raw `TextInput::make('logo_path')` strings instead of actual previews. New `VendorFileLinks` helper renders thumbnails for images + signed-URL download links for private docs. |
| 3. Admin can't see selected vendor package | **FIXED** | `VendorResource` form had no display surface for the vendor's pre-approval package choice. v10.1 adds a "Vendor-selected package" section + a "Requested package" column to the table. |
| 4. Product creation crashes with `MassAssignmentException` | **FIXED** | `VendorProductController::store/update` passed `$request->validate([..., 'images' => [...]])` straight into `Product::create($data)`. Since `images` isn't a Product column (and shouldn't be), Eloquent threw at every product create attempt. v10.1 `unset($data['images'])` before `Product::create`. |
| 5. Vendor can't update order status | **FIXED** | Controller actions (ship/confirm/deliver) + routes existed, but the only UI to invoke them was on the order detail page. Vendor orders **list** page had no inline actions. v10.1 adds row-level buttons. |
| 6. Admin reports page missing/unfindable | **FIXED (was broken)** | `Admin/Reports/Index.tsx` imported `AdminLayout` from `@/Layouts/AdminLayout` — but that file **did not exist** in v10.0. The page module failed to resolve at build time. The dev couldn't find admin reports because the page literally couldn't render. v10.1 creates `AdminLayout.tsx` + registers a Filament navigation item linking to /admin/reports. |
| 7. Vendor reports page missing/unfindable | **FIXED** | `VendorLayout.tsx` had no link to `/vendor/reports`. v10.1 adds it inline plus into the new mobile drawer. |
| 8. /sitemap.xml doesn't exist | **VERIFIED PRESENT** | Route + controller are present in the v10.0 archive (`grep -n 'sitemap' routes/web.php` returns line 370). Most likely cause of the dev's report: nginx tried to serve `/sitemap.xml` as a static file and 404'd before reaching Laravel. **The deployment guide already warns about this** but v10.1 adds clearer guidance + a CI test that hits the route. If still 404 in deployment, see `TROUBLESHOOTING.md` Phase 10 v10.1 section. |
| 9. Images shown as paths | **FIXED (vendor uploads)** | Same root cause as #2. Resolved across `VendorResource`. Product images already had proper rendering on the storefront via `Storage::url`. |
| 10. Mobile responsiveness broken | **FIXED** | `StorefrontLayout` + `VendorLayout` were inline-flex with 10+ items, overflowing < ~700px viewports. v10.1 collapses to hamburger menus at `< md` (storefront) / `< lg` (vendor). Tables use `overflow-x-auto`. Touch targets ≥ 40px. |
| 11. Deferred items 16/24/26/28 documented | **DONE** | See `PHASE_10_KNOWN_LIMITATIONS.md` updated section. |

---

## The two confirmed root-cause bugs

### Bug A — MassAssignmentException on every product create

**Location:** `app/Http/Controllers/Vendor/VendorProductController.php::store` line 112 (also `::update`).

```php
$data = $request->validate([
    'name' => [...],
    // ...
    'images'   => ['nullable', 'array', 'max:10'],
    'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
]);

// BUG: $data['images'] is an array of UploadedFile objects.
// 'images' isn't in Product::$fillable — Eloquent throws.
$product = Product::create(array_merge($data, [...]));
```

**Why it survived previous tests:** the existing vendor product tests don't always exercise the `images` field — when no images are submitted, `$data['images']` is unset by `validate()` and `Product::create` succeeds. The crash only happens when images ARE submitted (which the developer's manual test did).

**Fix:** unset `images` from `$data` immediately after validation, before `Product::create`. The uploaded files are read separately from `$request->file('images')`.

**Guard:** v10.1 CI sub-check (`Phase 10 v10.1 — Product images mass-assignment`) refuses any code change that removes the unset.

### Bug B — admin reports page literally couldn't render

**Location:** `resources/js/Pages/Admin/Reports/Index.tsx` line 3:

```typescript
import AdminLayout from '@/Layouts/AdminLayout';   // ← file does not exist
```

**Why it survived shipping:** my v10.0 build sandbox couldn't run `npm run build` (no network → no `npm ci`). I shipped the React page without verifying the imports resolved. Real Vite would have failed at build time; the developer's `npm run build` either silently failed for this page module or produced an unusable page that returned a blank screen.

**Fix:** create `AdminLayout.tsx` (mirrors `VendorLayout.tsx` pattern; includes mobile hamburger).

**Guard:** v10.1 CI sub-check (`Phase 10 v10.1 — Reports navigation links present in layouts`) refuses to pass if `AdminLayout.tsx` is missing.

---

## v10.1 files

```
NEW:
  resources/js/Layouts/AdminLayout.tsx                                    — the missing layout
  app/Domain/Vendor/VendorFileLinks.php                                   — preview HTML helper
  app/Http/Controllers/Admin/VendorFileController.php                     — signed-URL file viewer
  database/migrations/2026_06_15_000001_add_phase10_v101_performance_indexes.php
  tests/Feature/Phase10V101RegressionTest.php                             — 14 v10.1 scenarios

MODIFIED:
  app/Http/Controllers/Vendor/VendorProductController.php                 — unset images (×2)
  app/Filament/Resources/VendorResource.php                               — viewable files + package display
  app/Providers/Filament/AdminPanelProvider.php                           — Reports nav item
  app/Http/Middleware/HandleInertiaRequests.php                           — translations cached
  resources/js/Layouts/VendorLayout.tsx                                    — Reports link + mobile menu
  resources/js/Layouts/StorefrontLayout.tsx                                — mobile menu
  resources/js/Pages/Vendor/Orders/Index.tsx                              — inline action buttons
  routes/web.php                                                           — vendor-files signed route
  .github/workflows/ci.yml                                                 — 7 new sub-checks
  VERSION                                                                  — Phase 10 → Phase 10 v10.1
  README.md                                                                — header + status block
  PHASE_10_REPORT.md                                                       — v10.1 correction section
  PHASE_10_VERIFICATION_MATRIX.md                                          — v10.1 update
  PHASE_10_KNOWN_LIMITATIONS.md                                            — deferred items 16/24/26/28
  PHASE_10_DEVELOPER_TESTING_CHECKLIST.md                                 — re-test instructions
  TROUBLESHOOTING.md                                                       — v10.1 entries
```

## Counts

| | v10.0 → v10.1 |
|---|---|
| Phase 10 CI sub-checks | 6 → **13** (6 v10.0 + 7 v10.1) |
| Phase 10 Pest scenarios | 13 → **27** (13 + 14) |
| Phase-specific CI grand total | 61 → **68** (13 + 20 + 22 + 13) |
| Unique global helpers | 48 → **50** (2 new `p101_`-prefixed) |
| New production PHP files | — | 2 (VendorFileLinks, VendorFileController) |
| New React files | — | 1 (AdminLayout) |
| Files modified by v10.1 | — | 10 |
| **0 v1-v9 file modified by v10.1** | | ✓ |

## What is NOT fixed in v10.1

These items remain limitations and are documented as such in `PHASE_10_KNOWN_LIMITATIONS.md`:

- **Full N+1 query audit across all controllers** — v10.1 covered the hottest paths (reports, catalog, sitemap). A deeper audit using Telescope or Debugbar should run in staging before deployment.
- **Image CDN / signed S3 URLs for product images** — current setup uses local storage + storage:link. Works fine for moderate traffic; needs CDN for scale.
- **Real backup test against production-like data** — backup script is documented but quarterly DR test (per backup guide) hasn't been simulated.
- **Accessibility deep audit** — v10.1 keeps spot-check guidance; full WCAG 2.1 AA audit + screen-reader testing is a separate workstream.
- **PDF report export** — explicit scope cut.
- **Queued large exports** — explicit scope cut.

## Final CI verdict

```
✅ Phase 10 v10.1 PASSES — ready for final deployment review
```

Only after the actual CI run produces this line is v10.1 a valid release.

**Phase 10 v10.1 STOPS HERE. No Phase 11. No "publicly launched" declaration.**
