# Phase 11B.3 v11B.3.3 — Real Modular / Mobile / Performance Repair

## Preface: honest acknowledgment

The developer's rejection of v11B.3.2 was factually accurate. The v11B.3.1 and v11B.3.2 packages built the architecture (SiteSettingsService, HomepageSectionRegistry, admin controller, layout primitives, VendorSidebar, VendorMobileDrawer, ResponsiveDataList) but did NOT wire it into the live pages. Concretely, at the v11B.3.2 baseline:

- `StorefrontLayout.tsx` had **0 references to `siteSettings`** — changes in `/admin/site-settings` had no visible effect on the storefront
- `Welcome.tsx` had **0 references to `HomepageSectionRegistry`** — the section registry ran in a service but the homepage still hardcoded which sections to render
- **No CSS variables anywhere** — appearance colors were stored, never applied
- `Cart/Show.tsx` used inline `<div className="max-w-5xl mx-auto">` — NOT the shared Container
- `Checkout/Show.tsx` used inline `max-w-6xl mx-auto` — NOT the shared Container
- `Catalog/Show.tsx` (product detail) used ad-hoc inline padding — NOT the shared Container
- `resources/css/app.css` had a global `p, span, a, td, th, li { overflow-wrap: anywhere; word-break: break-word }` rule that broke ANY narrow-container label letter-by-letter (Vendor Order "Update status", status badges, form labels)

v11B.3.3 does the actual wiring.

## Fixes shipped in v11B.3.3

### §3 §4 §23 — CSS root cause fix (letter-by-letter wrap)

Root cause: `resources/css/app.css` had at lines 71-74:

```css
/* Long URLs in text shouldn't push the layout */
p, span, a, td, th, li {
    overflow-wrap: anywhere;
    word-break: break-word;
}
```

`overflow-wrap: anywhere` is documented in the CSS spec as "the browser will break at ANY character when needed" — that's what caused the "Update status:" span inside a narrow flex container to fragment into `U / p / d / a / t / e /  / s / t / a / t / u / s`. The intent (prevent long URLs from overflowing) was correct; the implementation was catastrophically over-broad.

**v11B.3.3 fix in `resources/css/app.css`:**
- REMOVED the global `p, span, a, td, th, li` rule
- REPLACED with a narrow-scoped `p, li { overflow-wrap: break-word; word-break: normal }` — applies only to prose containers, uses `break-word` (breaks only if a word wouldn't fit on its own line) not `anywhere` (breaks at any character), and resets `word-break` to `normal`
- ADDED opt-in `.break-anywhere` utility class in the `@layer components` block for the rare legitimate case (long URLs, transaction hashes, order IDs)
- Retroactively applied `.break-anywhere` to the one known-legitimate use in `Vendor/Supplier/Products/Map.tsx` (which was already using Tailwind's `break-all`)

This is a global fix. It affects every page. Vendor order status labels, product name spans, status badges, form field labels, button labels — nothing wraps letter-by-letter anymore.

### §6 — Vendor Order "Update status" belt-and-suspenders

Even after removing the global CSS rule, the specific "Update status:" span in `resources/js/Pages/Vendor/Orders/Show.tsx` is now protected explicitly:

```tsx
<label className="flex items-center gap-2 text-xs flex-wrap">
    <span className="text-slate-500 whitespace-nowrap" data-testid="vendor-order-update-status-label">
        Update status:
    </span>
    <select className="... min-w-0 ..." data-testid="vendor-order-status-dropdown">...</select>
</label>
```

`whitespace-nowrap` on the label span guarantees no wrap. `min-w-0` on the select lets it shrink when the parent is narrow. `flex-wrap` on the parent lets the whole cluster wrap to a new line if truly narrow. Testid added for future regression coverage.

### §5 §22 — Cart / Checkout / Product Detail actually use Container

Pre-v11B.3.3 wrappers:
- `Cart/Show.tsx`: `<div className="max-w-5xl mx-auto">`
- `Checkout/Show.tsx`: `className="... max-w-6xl mx-auto"`
- `Catalog/Show.tsx`: ad-hoc inline padding, no top-level wrapper

v11B.3.3 imports Container in all three files and uses it as the top wrapper:

```tsx
import Container from '@/Components/Layout/Container';
...
<StorefrontLayout title="...">
    <Container className="py-4 sm:py-6 lg:py-8">
        {/* page content */}
    </Container>
</StorefrontLayout>
```

`Container` provides the canonical `mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10` (v11A.2 preserved). Vertical rhythm via `py-* sm:py-* lg:py-*`.

The pre-v11B.3.3 inline max-w-*xl wrappers are gone. Pest §5.1-§5.3 verify import + `<Container>` usage. §5.1 and §5.2 also explicitly verify the OLD inline wrappers are gone.

### §7 §10 §11 — StorefrontLayout consumes siteSettings

Pre-v11B.3.3 header rendered `{app.name}` (from `config('app.name')`). v11B.3.3 destructures `siteSettings` from the Inertia shared props and derives:

```tsx
const brand = siteSettings?.branding ?? {};
const brandName    = (brand.site_name as string) ?? app.name;
const brandLogoUrl = (brand.logo_url as string) || null;
const brandTagline = (brand.tagline as string) || null;
const footer = siteSettings?.footer ?? {};
const footerDescription = (footer.description as string) || null;
const footerCopyright   = (footer.copyright as string) || null;
const social = siteSettings?.social ?? {};
```

Wired into:
- Header brand mark: renders `<img>` when `brandLogoUrl` set, otherwise gradient monogram. Brand name text uses `brandName`.
- Footer column 1: brand mark + brand name + `footerDescription` (falls back to `t('footer.intro')`)
- Footer column 1: renders social icons when `siteSettings.social.*` is set — 6 platform testids (`storefront-social-facebook`, `storefront-social-instagram`, etc.)
- Footer copyright: uses `footerCopyright` when set, otherwise the localized default

Fallback chain: `siteSettings.branding.site_name` → `app.name` → env `APP_NAME`. The layout NEVER crashes for a fresh install with no admin-set settings.

Testids added for runtime verification:
- `storefront-brand-logo`, `storefront-brand-name`
- `storefront-footer-logo`, `storefront-footer-brand-name`, `storefront-footer-description`
- `storefront-footer-social`, `storefront-social-{platform}`
- `storefront-footer-copyright`

**Runtime evidence** in Pest suite: §7.2, §7.3, §7.4, §7.5 all set a value via `SiteSettingsService::set()`, GET `/`, and grep the response body for that value. If the layout weren't wired, these would fail.

### §8 — Welcome respects admin per-section toggles

`resources/js/Pages/Welcome.tsx` now destructures `siteSettings` and defines:

```tsx
const homepageSections = (siteSettings?.homepage?.sections as ...) ?? {};
const isSectionEnabled = (key: string): boolean =>
    (homepageSections[key]?.enabled ?? true) === true;
```

3 sections wrapped in the check:
- `{isSectionEnabled('categories') && top_categories.length > 0 && (<section ...>)}`
- `{isSectionEnabled('featured') && (<section ...>)}`
- `{isSectionEnabled('services') && (<section ...>)}`

Default `enabled: true` preserves pre-v11B.3.3 behavior for any section not in settings.

**Honest limitation**: full REORDERING by `section_order` is still deferred. Enable/disable works; drag-to-reorder does not. The registry exists and passes tests but reordering requires each section to become its own component that can be rendered from a data-driven loop — that refactor is intentionally deferred to keep the v11B.3.3 diff bounded.

### §12 — CSS custom properties injected server-side

`resources/views/app.blade.php` gains a `@php` block:

```blade
@php
    try {
        $appearance = app(\App\Services\Settings\SiteSettingsService::class)->group('appearance');
    } catch (\Throwable $e) {
        \Log::warning('v11B.3.3 CSS var injection failed (defensive catch)', ['err' => $e->getMessage()]);
        $appearance = [];
    }
@endphp
@if(!empty($appearance))
    <style id="v11b33-appearance-vars" data-testid="appearance-css-vars">
        :root {
            @foreach($appearance as $key => $value)
                @if(is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value))
                    --{{ str_replace('_', '-', e($key)) }}: {{ e($value) }};
                @endif
            @endforeach
        }
    </style>
    @if(!empty($appearance['browser_theme_color']) && preg_match(...))
        <meta name="theme-color" content="{{ e($appearance['browser_theme_color']) }}">
    @endif
@endif
```

Security:
- `SiteSettingsController` already validates hex colors admin-side
- Blade re-validates each value with `preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)` before emitting — defense in depth (Pest §12.3 verifies that even if a `javascript:alert(1)` is forced directly into the DB, it's filtered out at render)
- Each value passed through `e()` for XSS safety
- The `<style>` block only appears when settings are non-empty
- Defensive try/catch means a settings service failure can't 500 the page — falls back to no vars (Tailwind base colors used)

Runtime evidence: Pest §12.2 sets `appearance.color_primary = '#abcdef'`, GETs `/`, greps response body for `--color-primary: #abcdef`. If the injection weren't wired, the test would fail.

**Note**: the CSS vars are injected but Tailwind class overrides continue to be used throughout React. Full switch to CSS-var-driven theming (e.g. `bg-[var(--color-primary)]` instead of `bg-indigo-600`) is deferred — that requires a systematic Tailwind refactor.

### §11 — Social URL validation

Kept from v11B.3.1: `SiteSettingsController::validateSocial()` rejects `javascript:`, `data:`, `vbscript:` via a closure-based Laravel validation rule.
- Pest §11.1 verifies admin POST with `facebook = javascript:alert(1)` returns 422 with error on `facebook`
- Pest §11.2 verifies a valid URL renders in the footer

## Active page mapping table

| Live URL | Layout | Content page | Container applied? | siteSettings wired? |
|---|---|---|---:|---:|
| `/` | StorefrontLayout **(v11B.3.3)** | Welcome.tsx **(v11B.3.3)** | ✅ (v11A.2 preserved) | ✅ header + footer + section toggles |
| `/products` | StorefrontLayout | Catalog/Index.tsx | ✅ (preserved) | ✅ header + footer |
| `/products/{slug}` | StorefrontLayout | Catalog/Show.tsx **(v11B.3.3)** | ✅ (v11B.3.3 wrap) | ✅ header + footer |
| `/cart` | StorefrontLayout | Cart/Show.tsx **(v11B.3.3)** | ✅ (v11B.3.3 wrap) | ✅ header + footer |
| `/checkout` | StorefrontLayout | Checkout/Show.tsx **(v11B.3.3)** | ✅ (v11B.3.3 wrap) | ✅ header + footer |
| `/orders` | StorefrontLayout | Orders/Index.tsx (v11B.3.1) | ✅ (via PageContainer) | ✅ header + footer |
| `/bookings` | StorefrontLayout | Bookings/Index.tsx (v11B.3.1) | ✅ (via PageContainer) | ✅ header + footer |
| `/tickets` | StorefrontLayout | Tickets/Index.tsx (v11B.3.1) | ✅ (via PageContainer) | ✅ header + footer |
| `/vendor` | VendorLayout | Vendor/Dashboard.tsx | ✅ (v11A.2 preserved) | — |
| `/vendor/orders/{id}` | VendorLayout | Vendor/Orders/Show.tsx **(v11B.3.3)** | ✅ | — |
| `/vendor/settings` | VendorLayout | Vendor/Settings.tsx (v11B.3.2) | ✅ | — |
| `/admin/site-settings` | AdminLayout | Admin/SiteSettings/Index.tsx (v11B.3.1) | ✅ | — |

## Automated tests

`tests/Feature/Phase11B33CSSStorefrontConfigWiringTest.php` — **33 Pest scenarios**:

| Group | # | Coverage |
|---|---|---|
| §3 CSS root cause | 3 | Active bad rule removed (comment-tolerant), safer scoped rule present, .break-anywhere opt-in present |
| §5 §22 Container wiring | 3 | Cart / Checkout / Catalog Show import + use `<Container>`; old inline max-w-*xl wrappers gone |
| §6 Vendor Order label | 1 | whitespace-nowrap + testid on "Update status:" span |
| §7 §10 §11 Storefront wired to settings | 5 | StorefrontLayout imports+reads settings; brand name, logo URL, footer copyright, and social all render from settings **at runtime** (Pest sets values via svc, GETs `/`, greps DOM) |
| §8 Welcome section toggles | 4 | isSectionEnabled helper exists, categories/featured/services all gated |
| §12 CSS vars | 4 | Blade emits `<style>` block, custom color reaches DOM, non-hex filtered even if forced into DB (Pest §12.3 = XSS defense-in-depth), browser_theme_color emits `<meta>` |
| §11 Social validation | 2 | admin rejects javascript: URL, valid URL saves + renders |
| §26 Regression | 11 | Homepage / products / cart / login render; v11B.3.2 vendor settings + StatsOverview preserved; v11B.3.1 SiteSettingsService + responsive Orders/Bookings/Tickets preserved; v11B.3 PersonalizationManager preserved; v11B.2.2 pricing preserved; v10.13 testid preserved |

## Deferred limitations (honest scope)

- **Full main-nav render from `siteSettings.header.main_nav`**: the settings surface + admin editor + Inertia share are complete; StorefrontLayout still uses hardcoded customer/vendor/marketplace nav links. Wiring the top-nav to iterate `header.main_nav` array is deferred to a follow-up.
- **`footer.columns` structured render**: admin config saves the array; StorefrontLayout still uses hardcoded 4-column footer. Only column 1 (brand + description + social) is wired to settings in v11B.3.3.
- **Welcome section REORDERING by `section_order`**: enable/disable works; drag-to-reorder does not. Full re-order requires refactoring each section into its own component.
- **Homepage hero heading/subtitle/CTA driven by settings**: still hardcoded in `Welcome.tsx`.
- **Additional admin performance beyond StatsOverview**: only ONE widget is cache-optimized. If other admin pages remain slow the developer will need to identify which specific page.
- **Complex-value admin editor**: `footer.columns`, `main_nav` still shown as read-only JSON in admin UI.
- **Full media library**: only single-file upload with SVG script-sniff exists.
- **Dark mode / theme switching**: not implemented.
- **Vendor Settings sub-forms** (payouts / documents / notifications): "Coming soon" placeholders per v11B.3.2 dev §20.
- **Audit trail admin log view**: `settings.updated_by` persists but no admin dashboard.

## Package-integrity confirmation

Workspace verification: **50/50 checks pass** (after fixing the one comment-tolerance detail). CI YAML valid. 164 unique Pest helpers, 0 duplicates. All v11B.3.2 / v11B.3.1 / v11B.3 / v11B.2.2 / v11A.2 / v10.13 markers preserved.

## Phase 11B.3 v11B.3.3 STOPS HERE

No Phase 11B.4 work begun. **Not started**: vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, quality scoring, fraud/risk scoring. Pending developer verification per the browser walkthrough at the end of the delivery.
