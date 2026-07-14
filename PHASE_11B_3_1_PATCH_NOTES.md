# Phase 11B.3 v11B.3.1 — Patch Notes

## Summary

Modular site-settings architecture + consistent mobile page padding + responsive Orders / Bookings / Support + dedicated vendor navigation side panel. No paid AI APIs. No financial/pricing/personalization changes.

## What's new for admins

- `/admin/site-settings` — tabbed page (Branding / Appearance / Header / Homepage / Footer / Contact / Social / SEO / Mobile)
- Change site name, logos, favicon, colors, footer text, social links **without editing source code**
- Save is transactional; cache invalidates immediately — no `optimize:clear` needed
- Reset any group to config defaults with one click
- Translatable values (tagline, hero heading, footer description) editable in English + Arabic side-by-side
- All changes audited via `settings.updated_by` (FK to `users`)

## What's new for customers

- **Orders / Bookings / Support** now use responsive cards on mobile instead of a squeezed 6-column desktop table
- Consistent 16-px mobile gutter across every page via the canonical `Container` (v11A.2 preserved)
- Vendor storefront pages have identical padding to customer pages

## What's new for vendors

- **Persistent side panel** on desktop (lg+): 7 groups (Overview / Catalog / Orders & bookings / Finance / Suppliers / Communication / Settings)
- **Slide-in drawer** on mobile with focus trap, Escape close, backdrop click, body scroll lock, RTL-aware direction
- Icons on every nav row for visual scannability
- Active-route highlighting via `aria-current="page"`
- Permission-aware: `requiresApproved` items hidden from non-approved vendors (server-side auth still enforced independently)

## Files added (14 new)

**Config + service (3)**:
- `config/site.php` — 9 default groups (branding, appearance, header, homepage, footer, contact, social, seo, mobile)
- `app/Services/Settings/SiteSettingsService.php` — canonical settings API
- `app/Services/Settings/HomepageSectionRegistry.php` — 8 section types + resolve()

**Migration (1 additive)**:
- `database/migrations/2026_09_01_000001_add_audit_and_translatable_to_settings.php`

**Controller (1)**:
- `app/Http/Controllers/Admin/SiteSettingsController.php`

**React (5)**:
- `resources/js/Components/Layout/PageContainer.tsx` (PageContainer + PageHeader + EmptyState)
- `resources/js/Components/Layout/ResponsiveDataList.tsx`
- `resources/js/Components/Vendor/VendorSidebar.tsx`
- `resources/js/Components/Vendor/VendorMobileDrawer.tsx`
- `resources/js/Pages/Admin/SiteSettings/Index.tsx`

**Tests + docs (5)**:
- `tests/Feature/Phase11B31ConfigMobileTest.php` (42 scenarios)
- `PHASE_11B_3_1_MODULAR_CONFIGURATION_MOBILE_UX_REPORT.md`
- `PHASE_11B_3_1_PATCH_NOTES.md`
- `PHASE_11B_3_1_DEVELOPER_CHECKLIST.md`
- `PHASE_11B_3_1_ROLLBACK.md`
- `PHASE_11B_3_1_PACKAGE_INTEGRITY.md`

## Files modified (7)

- `app/Http/Middleware/HandleInertiaRequests.php` — adds `siteSettings` shared prop via `SiteSettingsService::publicPayload()` with defensive fallback to config defaults
- `resources/js/Layouts/VendorLayout.tsx` — rewritten to use VendorSidebar + VendorMobileDrawer. Preserves `data-testid="vendor-nav-reports"` for v10.13 CI grep.
- `resources/js/Pages/Orders/Index.tsx` — refactored to use PageContainer + ResponsiveDataList + StatusBadge; adds `order-mobile-card` testid
- `resources/js/Pages/Bookings/Index.tsx` — refactored to same pattern; adds `booking-mobile-card` testid
- `resources/js/Pages/Tickets/Index.tsx` — refactored to same pattern; adds `ticket-mobile-card` testid
- `routes/web.php` — 4 new admin routes with regex group constraint + rate limiting
- `lang/en.json` + `lang/ar.json` — +29 keys each (site_settings.* + vendor_nav.*)
- `.github/workflows/ci.yml` — +8 v11B.3.1 sub-checks
- `VERSION` — `Phase 11B.3` → `Phase 11B.3 v11B.3.1`

## Counts

| Metric | v11B.3 → v11B.3.1 |
|---|---|
| CI sub-checks | 172 → **180** (+8) |
| Pest scenarios | 731 → **773** (+42) |
| Unique Pest helpers | 155 → **158** (+3 p11b31_*) |
| Migrations added | +1 additive |
| New services | +2 (SiteSettingsService, HomepageSectionRegistry) |
| New layout primitives | +2 (PageContainer, ResponsiveDataList) |
| New components | +2 vendor (VendorSidebar, VendorMobileDrawer) |
| Translation keys | +29 en + 29 ar |

## Required-result mapping (per directive)

| Required proof | Status |
|---|---|
| Routine branding changes without source-code edits | ✅ Admin page + service + migration |
| Site name / logos / favicon / colors / footer / social configurable | ✅ 9 groups defined in config/site.php |
| Homepage sections modular + reorderable + translatable | ✅ HomepageSectionRegistry + section_order setting |
| Settings update immediately with safe cache invalidation | ✅ SiteSettingsService.set() flushes cache in same call |
| Mobile padding consistent across pages | ✅ Canonical Container (v11A.2) preserved + enforced |
| Orders / Bookings / Support use mobile cards | ✅ ResponsiveDataList + testids per-page |
| Vendor navigation uses dedicated responsive side panel | ✅ VendorSidebar + VendorMobileDrawer |
| Arabic RTL across new components | ✅ `isRTL` detection in drawer, `dir="rtl"` in admin inputs, `end-0` positioning |
| No financial/search/recommendation/personalization/checkout regression | ✅ 42 Pest regression scenarios + all prior phases preserved |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate                                       # 1 new additive migration
php artisan migrate:status | grep 2026_09                 # confirm the new migration Ran=Yes
php artisan route:list | grep -i site-setting             # 4 admin routes
php artisan route:list | grep -i vendor                   # confirm vendor routes preserved
php artisan test --filter=Phase11B31                       # 42 v11B.3.1 scenarios
php artisan test --filter=Phase11B3                        # 56 v11B.3 regression
php artisan test --filter=Phase11B22                       # 45 v11B.2.2 regression
php artisan test                                            # 773 total
npm ci && npm run typecheck && npm run build
```

## Rollback

3-tier:

**Tier 1** — Revert code, keep the settings table:
```bash
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Http/Middleware/HandleInertiaRequests.php \
    marketplace/resources/js/Layouts/VendorLayout.tsx \
    marketplace/resources/js/Pages/Orders/Index.tsx \
    marketplace/resources/js/Pages/Bookings/Index.tsx \
    marketplace/resources/js/Pages/Tickets/Index.tsx \
    marketplace/routes/web.php
php artisan optimize:clear && npm ci && npm run build
```
The `updated_by` + `is_translatable` columns remain (harmless — they're nullable).

**Tier 2** — Full code revert + drop new migration:
```bash
php artisan migrate:rollback --step=1
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite
php artisan optimize:clear && npm ci && npm run build
```

**Tier 3** — Full revert to v11B.3 formally-approved baseline:
```bash
php artisan migrate:rollback --step=1
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite
rm -rf public/build node_modules/.vite
npm ci && npm run build
cat VERSION    # → Phase 11B.3
```

## Honest scope

✅ SiteSettingsService with grouped cache + immediate invalidation
✅ 9 default groups in config/site.php + admin editor with tabs
✅ Additive migration for updated_by + is_translatable
✅ HomepageSectionRegistry with 8 section types + feature-flag guards
✅ Admin controller with super_admin auth + safe URL + color regex + SVG script sniff
✅ Inertia siteSettings shared prop with defensive config fallback
✅ Container v11A.2 preserved as THE mobile padding standard
✅ PageContainer + PageHeader + EmptyState + ResponsiveDataList primitives
✅ Orders/Bookings/Tickets refactored with mobile card testids
✅ VendorSidebar (7 groups, icons, active state, permission-aware)
✅ VendorMobileDrawer (focus trap, Escape, backdrop, body scroll lock, RTL)
✅ VendorLayout rewritten preserving vendor-nav-reports testid
✅ 29 en + 29 ar translation keys
✅ 42 Pest scenarios + 8 CI sub-checks
✅ 3-tier rollback with baseline preservation

❌ NOT in v11B.3.1 (deferred, documented in REPORT):
- Complex-value editor in admin UI (footer.columns, main_nav shown as read-only JSON)
- Full media library integration (upload endpoint exists but React picker is simplified)
- Dark-mode / theme switching
- Server-side CSS custom property injection for appearance colors
- StorefrontLayout consumption of `siteSettings.footer.columns` and `header.main_nav` (settings surface complete, storefront refactor deferred)
- Welcome.tsx driven by HomepageSectionRegistry (registry exists + tests pass; Welcome still hardcodes render order)
- Product detail / Cart / Checkout mobile audits: Container already correctly applied — documented but no code changes required
- Audit trail admin log view

## Phase 11B.3 v11B.3.1 STOPS HERE

Not started: vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, quality scoring, fraud/risk scoring. Pending dev verification.
