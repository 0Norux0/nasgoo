# Phase 11B.3 v11B.3.3 — Patch Notes

## Summary

Real wiring of the v11B.3.1 + v11B.3.2 architecture into the live pages, plus a global CSS root-cause fix for letter-by-letter wrapping that had been affecting labels site-wide. This delivery does the work the v11B.3.1 and v11B.3.2 packages **claimed** to do but hadn't.

## What was broken pre-v11B.3.3 (honest)

- **CSS**: `resources/css/app.css` had `p, span, a, td, th, li { overflow-wrap: anywhere }` — global rule caused labels to fragment letter-by-letter in narrow containers (vendor Order "Update status", status badges, form labels).
- **Storefront**: `StorefrontLayout.tsx` had **0 references** to `siteSettings`. Changing site name in `/admin/site-settings` had no visible effect.
- **Homepage**: `Welcome.tsx` had **0 references** to `HomepageSectionRegistry`. Section order + enable toggles didn't work.
- **Colors**: no CSS variables anywhere. Admin-configured colors were stored but never applied.
- **Cart / Checkout / Product Detail**: used inline `max-w-*xl mx-auto` — NOT the shared Container.

## What v11B.3.3 fixes

- **CSS root cause**: `p, span, a, td, th, li` rule REMOVED. Replaced with scoped `p, li { overflow-wrap: break-word; word-break: normal }`. `.break-anywhere` utility added for opt-in per-character breaking (long URLs).
- **Storefront wiring**: `StorefrontLayout.tsx` destructures `siteSettings` from `usePage` props. Header brand mark uses `siteSettings.branding.site_name` + `logo_url`. Footer uses `footer.description`, `footer.copyright`, and renders 6 social icons from `siteSettings.social`.
- **Welcome toggles**: `Welcome.tsx` has `isSectionEnabled(key)` helper. Categories, Featured Products, Services sections gated by `siteSettings.homepage.sections[key].enabled`.
- **CSS var injection**: `resources/views/app.blade.php` emits `<style>:root { --color-primary: ...; }</style>` from `SiteSettingsService::group('appearance')` with hex-regex + XSS-safe `e()` filtering + defensive try/catch.
- **Container applied**: Cart/Show, Checkout/Show, Catalog/Show now import + use `<Container>`. Inline `max-w-5xl mx-auto` and `max-w-6xl mx-auto` wrappers gone.
- **Vendor Order label**: "Update status:" span gets `whitespace-nowrap` + testid. Select has `min-w-0`. Parent has `flex-wrap`.

## Runtime evidence

Every fix has a Pest test that PROVES the wiring works, not just that the file exists:
- Pest §7.2 sets `branding.site_name = 'MyCustomStoreV11B33'`, GETs `/`, expects response body to contain that name
- Pest §7.3 sets `branding.logo_url = '/images/my-custom-logo-v11b33.svg'`, GETs `/`, expects response body to contain the logo URL
- Pest §7.4 sets `footer.copyright = 'CustomCopyright2026'`, GETs `/`, expects response to contain it
- Pest §7.5 sets `social.facebook`, GETs `/`, expects the URL in the footer
- Pest §12.2 sets `appearance.color_primary = '#abcdef'`, GETs `/`, expects `--color-primary: #abcdef` in `<style>` block
- Pest §12.3 forces `javascript:alert(1)` directly into DB, verifies it's filtered out (defense in depth)

Non-runtime CSS check: Pest §3.1 scans app.css line-by-line skipping comment lines, verifies the old aggressive rule is not an active declaration.

## Files added (1 test file)

- `tests/Feature/Phase11B33CSSStorefrontConfigWiringTest.php` — 33 scenarios

## Files modified (10)

- `resources/css/app.css` — CSS root-cause fix
- `resources/views/app.blade.php` — CSS var injection from appearance settings
- `resources/js/Layouts/StorefrontLayout.tsx` — consumes siteSettings.branding + footer + social
- `resources/js/Pages/Welcome.tsx` — isSectionEnabled helper + 3 section gates
- `resources/js/Pages/Cart/Show.tsx` — Container wrap
- `resources/js/Pages/Checkout/Show.tsx` — Container wrap
- `resources/js/Pages/Catalog/Show.tsx` — Container wrap
- `resources/js/Pages/Vendor/Orders/Show.tsx` — whitespace-nowrap on Update status label
- `resources/js/Pages/Vendor/Supplier/Products/Map.tsx` — retroactive .break-anywhere utility class
- `resources/js/types/inertia.d.ts` — SharedProps.siteSettings typed
- `.github/workflows/ci.yml` — 7 v11B.3.3 sub-checks
- `VERSION` — `Phase 11B.3 v11B.3.2` → `Phase 11B.3 v11B.3.3`

## Counts

| Metric | v11B.3.2 → v11B.3.3 |
|---|---|
| CI sub-checks | 186 → **193** (+7) |
| Pest scenarios | 810 → **843** (+33) |
| Unique Pest helpers | 162 → **164** (+2 p11b33_*) |
| Migrations added | 0 (this phase is CSS/JS/Blade only) |
| Localization keys | 0 (no new UI strings) |

## Deploy commands

```bash
php artisan optimize:clear
# No migrations
php artisan test --filter=Phase11B33     # 33 v11B.3.3 scenarios
php artisan test --filter=Phase11B32     # 37 v11B.3.2 regression
php artisan test --filter=Phase11B31     # 42 v11B.3.1 regression
php artisan test                          # 843 total
npm ci && npm run typecheck && npm run build
```

## After deploy — dev's browser verification checklist

1. Load `/admin/site-settings`
2. In Branding tab: set `site_name = "TestStore"`, upload a logo (or paste URL to any image), save
3. In Appearance tab: set `color_primary = #ff0000`, save
4. In Homepage tab: toggle `sections.featured.enabled` to false, save
5. Load `/` **without clearing cache**. Expect:
   - Header shows "TestStore" (not the env `APP_NAME`)
   - Logo image visible next to the name
   - Featured Products section is missing
   - Page source contains `<style id="v11b33-appearance-vars">:root { --color-primary: #ff0000; ...`
6. Load `/vendor/orders/{any_id}` on a 375px viewport as an approved vendor. "Update status:" renders horizontally, not letter-by-letter.
7. Load `/cart` at 375px. Content is not overflowing horizontally. Gutters 16px each side.
8. Load `/checkout` at 375px. Same.
9. Load `/products/{any_slug}` at 375px. Same.

## Rollback

Tier 1 (revert the CSS only — restores letter-by-letter wrap):
```bash
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz \
    --strip-components=1 --overwrite marketplace/resources/css/app.css
npm ci && npm run build
```

Tier 2 (revert all v11B.3.3 code):
```bash
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz \
    --strip-components=1 --overwrite
php artisan optimize:clear
npm ci && npm run build
cat VERSION   # → Phase 11B.3 v11B.3.2
```

No schema change to roll back. See `PHASE_11B_3_3_ROLLBACK.md` for full detail.

## Honest scope

✅ CSS letter-by-letter wrap ROOT CAUSE fixed globally (app.css)
✅ Vendor Order "Update status" wrap protected (whitespace-nowrap + testid)
✅ Cart / Checkout / Product Detail actually use Container (inline max-w-*xl wrappers removed)
✅ StorefrontLayout actually consumes siteSettings.branding, footer.*, social.* (6 testids added for runtime verification)
✅ CSS custom properties injected server-side from appearance settings (with hex regex + XSS-safe e() + defensive catch)
✅ Welcome respects `isSectionEnabled(key)` for categories/featured/services
✅ SharedProps typed with siteSettings
✅ 33 Pest scenarios that verify RUNTIME wiring (not just file existence)
✅ 7 CI sub-checks
✅ All v11B.3.2 / v11B.3.1 / v11B.3 preservation markers intact

❌ NOT in v11B.3.3 (deferred, documented in REPORT):
- Full main nav render from siteSettings.header.main_nav (only footer column 1 is wired)
- Footer columns 2/3/4 structured render (only column 1 wired)
- Welcome section REORDERING by section_order (only enable/disable works)
- Homepage hero heading/subtitle/CTA driven by settings (still hardcoded)
- Additional admin performance beyond StatsOverview
- Complex-value admin editor
- Full media library
- Dark mode / theme switching
- Vendor Settings sub-forms
- Audit trail admin log view

## Phase 11B.3 v11B.3.3 STOPS HERE
