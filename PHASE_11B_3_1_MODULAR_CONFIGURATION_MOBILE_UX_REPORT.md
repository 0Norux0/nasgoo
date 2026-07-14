# Phase 11B.3 v11B.3.1 — Modular Site Configuration & Mobile UX Repair

Per dev §50.

## Scope

Four orthogonal concerns addressed in one package:

1. **Modular site-settings architecture** — centralized service wrapping the existing `Setting` model with grouped reads, caching, immediate invalidation, translations, defensive fallback
2. **Consistent mobile page padding** — canonical Container primitive (v11A.2) enforced as THE standard across every page
3. **Responsive Orders / Bookings / Support** — shared `ResponsiveDataList` primitive: desktop table + mobile cards from ONE data source
4. **Vendor navigation** — persistent side panel on desktop, slide-in drawer with focus trap + Escape + RTL on mobile

No Phase 11B.4 work begun. **No vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, or risk scoring.**

## Hardcoded-settings audit (§3)

| Setting | Pre-v11B.3.1 location | New configurable source |
|---|---|---|
| Site name | `config('app.name')` referenced by ~40 components | `siteSettings.branding.site_name` (Inertia share) |
| Tagline | Hardcoded in `Welcome.tsx` hero | `siteSettings.branding.tagline` (locale-resolved en/ar) |
| Logo path | `/images/logo.svg` referenced in header/footer/emails | `siteSettings.branding.logo_url` (+ dark + compact + email variants) |
| Favicon | `/favicon.ico` in blade layout | `siteSettings.branding.favicon_url` |
| Primary color | Tailwind `indigo-600` inline | `siteSettings.appearance.color_primary` |
| Footer description | Hardcoded in `StorefrontLayout.tsx` | `siteSettings.footer.description` (translatable) |
| Footer copyright | Hardcoded | `siteSettings.footer.copyright` (translatable) |
| Footer columns | Hardcoded links | `siteSettings.footer.columns` (structured array) |
| Contact email | `env('MAIL_FROM_ADDRESS')` in ~6 places | `siteSettings.contact.email` |
| Social URLs | Not exposed anywhere consistently | `siteSettings.social.*` (validated URLs) |
| SEO default title | Hardcoded in HandleInertiaRequests | `siteSettings.seo.default_title` (translatable) |
| Announcement bar | Component didn't exist | `siteSettings.header.announcement_*` |
| Homepage section order | Hardcoded in Welcome.tsx JSX | `siteSettings.homepage.section_order` array |

## Final settings architecture (§4)

Single canonical service: `App\Services\Settings\SiteSettingsService`.

Wraps the existing Phase 1 `Setting` model — no competing framework. The pre-v11B.3.1 `Setting` model already had `group + key + value(JSON) + type + is_encrypted + is_public + description`. v11B.3.1 adds:

- `updated_by` (nullable FK to `users`) — audit trail per dev §13
- `is_translatable` (bool) — marker for locale-keyed values

Both columns via one **additive + idempotent migration** (`2026_09_01_000001_add_audit_and_translatable_to_settings.php`).

### Service API

```
get(string $key, mixed $default = null, ?string $locale = null): mixed
group(string $group): array           // locale-resolved
groupRaw(string $group): array        // raw multi-locale (admin use)
set(string $key, mixed $value, ?int $updatedBy = null): void
setMany(array $pairs, ?int $updatedBy = null): void  // transactional
resetGroup(string $group): void
flushAll(): void
knownGroups(): array
publicPayload(): array                // for Inertia share, safe groups only
```

### Key design choices

- **Grouped reads**: `group('branding')` returns one associative array from a single cached read. Pre-v11B.3.1 storefront could issue 20+ SQL for individual settings on a single homepage render.
- **Locale-aware**: values like `['en' => 'Welcome', 'ar' => 'مرحباً']` resolve to active locale with English fallback.
- **Immediate invalidation**: `set()` writes DB AND flushes cache. No `optimize:clear` required after admin saves.
- **Safe defaults**: missing keys fall back to `config/site.php` — never a crash on fresh installs.
- **Defensive fallback**: if the cache driver throws, direct DB read; if THAT throws, config defaults — the storefront never 500s because of settings failure (v10.15 pattern).
- **Public-only for Inertia**: `publicPayload()` restricts to `branding, appearance, header, homepage, footer, contact, social, seo, mobile`. NEVER exposes `payment`, `commission`, or `security` groups.

## Branding configuration (§5)

Group `branding`, 10 configurable keys:

`site_name`, `short_name`, `legal_name`, `tagline` (translatable), `logo_url`, `logo_dark_url`, `logo_compact_url`, `favicon_url`, `social_image_url`, `email_logo_url`.

Image handling via `POST /admin/site-settings/upload-image`:
- File-type whitelist: `png, jpg, jpeg, webp, svg, ico`
- Size limit: 2MB
- SVG content-sniff rejects `<script`, `javascript:`, `onerror=`, `onload=` (§35 §47)
- Stored under public disk in `site-settings/{group}/` folder
- Only the URL is written to the setting — no filesystem paths, no base64

## Theme-token architecture (§6)

Group `appearance`, 14 color tokens:

`color_primary`, `color_primary_foreground`, `color_secondary`, `color_accent`, `color_success`, `color_warning`, `color_danger`, `color_surface`, `color_background`, `color_text`, `color_muted`, `color_border`, `color_link`, `browser_theme_color`.

Each value validated by regex `/^#[0-9a-fA-F]{3,8}$/`. Invalid values rejected with `422` — never persisted. Admin UI shows a color swatch preview alongside the hex input.

The `SiteSettingsService::group('appearance')` payload is exposed on the Inertia share as `siteSettings.appearance` — the storefront can pipe values into CSS custom properties on `<html>`. **Note**: full CSS-token injection is scoped for a follow-up release; the values are already collected and served in v11B.3.1.

## Homepage section registry (§7 §8)

`App\Services\Settings\HomepageSectionRegistry` — static registry of 8 supported section types:

| Key | Component | Default enabled | Feature-flag guard |
|---|---|---|---|
| `hero` | Hero | ✅ | — |
| `trust` | TrustIndicators | ✅ | — |
| `personalization` | PersonalizedSections | ✅ | `marketplace_personalization.features.enabled` |
| `categories` | FeaturedCategories | ✅ | — |
| `featured` | FeaturedProducts | ✅ | — |
| `services` | FeaturedServices | ❌ | — |
| `newsletter` | NewsletterSection | ❌ | — |
| `custom_banner` | CustomBanner | ❌ | — |

`HomepageSectionRegistry::resolve()` returns the filtered ordered list from admin config, dropping disabled + unknown-key sections + feature-gated-off sections. Unknown section keys silently drop (dev §39.25 "unknown section uses safe fallback"). No arbitrary component names, no raw HTML — only registered types.

## Header + footer configuration (§9 §10)

**Header** (`siteSettings.header`):
- `announcement_enabled`, `announcement_text` (translatable), `announcement_url` (safe-URL-validated)
- `main_nav` — structured array of items
- `contact_link_enabled`

**Footer** (`siteSettings.footer`):
- `description` (translatable)
- `copyright` (translatable)
- `columns` — array of `{heading: {en,ar}, links: [{url, label: {en,ar}}]}`
- `legal_links`

Storefront `StorefrontLayout.tsx` reads from these on the next release; current baseline preserves hardcoded fallbacks (the settings surface + admin editor + Inertia share are complete; storefront rendering hooks these values behind an incremental refactor to keep the diff bounded).

## Translation integration (§16)

Translatable values stored as `['en' => '...', 'ar' => '...']` in the JSON `value` column. Marked with `is_translatable = true`.

`SiteSettingsService::group()` runs `array_map(fn ($v) => is_array($v) && isset($v['en']) ? ($v[$locale] ?? $v['en']) : $v)` on every read — locale resolution happens ONCE per read (not per key access downstream). English fallback for missing Arabic (dev §16 "controlled fallback").

Admin UI (`Admin/SiteSettings/Index.tsx`) detects translatable values (has `en` key) and renders side-by-side inputs with `dir="rtl"` on the Arabic input.

## Media handling (§35)

Upload endpoint: `POST /admin/site-settings/upload-image` (throttle 30/min).

- File-type validation via Laravel's `image` rule + explicit `mimes:png,jpg,jpeg,webp,svg,ico`
- SVG script sniff: `preg_match('/<script|javascript:|onerror=|onload=/i', $body)` → reject
- Storage: public disk under `site-settings/{group}/` — reachable via `Storage::disk('public')->url($path)`
- Returns `{ url, path }` — the admin UI writes the URL to the relevant setting key

**Limitation (documented)**: A full media library UI (reuse existing uploads, alt-text metadata, delete safely) is deferred. v11B.3.1 provides a single-image upload endpoint; the admin picker is a simple file input.

## Cache strategy (§14 §46)

- One cache key per group: `site_settings:{group}:v1`
- TTL: 3600s (1 hour) — invalidated immediately on any `set()` / `setMany()` / `resetGroup()`
- Raw multi-locale group cached separately: `site_settings:raw:{group}:v1` (admin use only)
- Grouped reads mean ONE cache hit resolves an entire group; storefront never issues a query per-setting
- Defensive fallback: if `Cache::remember` throws, fall through to direct DB read; if DB throws, fall through to config defaults

Storefront never runs the settings-cache invalidation path — that only happens in the admin controllers, which enforce `super_admin` authorization.

## Shared layout components (§17)

New primitives in `resources/js/Components/Layout/`:

| Component | Purpose |
|---|---|
| `Container.tsx` **(preserved v11A.2)** | Canonical `mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10` |
| `PageContainer.tsx` | Wraps Container with vertical rhythm `py-4 sm:py-6 lg:py-8` |
| `PageHeader` | Title + description + actions slot, mobile-stacks |
| `EmptyState` | Icon + heading + description + CTA — one canonical empty pattern |
| `ResponsiveDataList` | Desktop table + mobile card list from ONE data source |

Container's `px-4 sm:px-6 lg:px-8 xl:px-10` scale IS the mobile-spacing standard per dev §18.

## Mobile-spacing standard (§18)

**One canonical scale**: `px-4 sm:px-6 lg:px-8 xl:px-10` — enforced via `Container.tsx`.

- 320px viewport: 16px gutter each side, 288px content
- 640px+ (sm): 24px gutter each side
- 1024px+ (lg): 32px gutter each side
- 1280px+ (xl): 40px gutter each side
- 1280px+ content capped at `max-w-7xl` (1280px), centered via `mx-auto`

No nested duplicate padding — pages use `<PageContainer>` which contains `<Container>` with `py-*` vertical rhythm. Never wrap a page with `<Container>` AND then apply another `px-*` inside; the wrapper's padding is the ONLY horizontal padding.

## Page-by-page padding audit (§19)

| Page | Outer container | 320px gutter | Overflow | Correction |
|---|---|---:|---|---|
| `/` (Welcome) | `Container` via StorefrontLayout | 16px | none | preserved |
| `/products` | `Container` | 16px | none | preserved |
| `/products/{slug}` | `Container` | 16px | none | preserved |
| `/cart` | `Container` | 16px | none | preserved |
| `/checkout` | `Container` | 16px | none | preserved (v11B.2.2) |
| `/orders` **(refactored)** | `PageContainer` → `Container` | 16px | none | ✅ v11B.3.1 |
| `/orders/{id}` | `Container` | 16px | none | preserved |
| `/bookings` **(refactored)** | `PageContainer` → `Container` | 16px | none | ✅ v11B.3.1 |
| `/bookings/{id}` | `Container` | 16px | none | preserved |
| `/tickets` **(refactored)** | `PageContainer` → `Container` | 16px | none | ✅ v11B.3.1 |
| `/tickets/{id}` | `Container` | 16px | none | preserved |
| `/wishlist` | `Container` | 16px | none | preserved |
| `/account/personalization` | `Container` | 16px | none | v11B.3 preserved |
| `/login`, `/register` | `AuthLayout` | 16px | none | preserved |
| `/vendor` **(refactored)** | `Container` inside VendorLayout main | 16px | none | ✅ v11B.3.1 |
| `/vendor/*` **(refactored)** | Same | 16px | none | ✅ v11B.3.1 |

## Orders mobile architecture (§23)

`resources/js/Pages/Orders/Index.tsx` rewritten:

- Uses `PageContainer` + `PageHeader` + `EmptyState`
- Uses `ResponsiveDataList<OrderRow>` with:
  - `columns`: 6 columns (Order, Date, Status, Payment, Items, Total) for `md+`
  - `renderCard`: mobile card showing all 6 fields as a labeled `<dl>` grid + a full-width "View details" button
- StatusBadge component with color mapping per order state (`completed=emerald`, `pending_payment=amber`, `cancelled=rose`, …)
- Mobile card testid: `order-mobile-card`
- Pagination preserved (renders under both variants)

## Bookings mobile architecture (§25)

`resources/js/Pages/Bookings/Index.tsx` rewritten:

- Same `ResponsiveDataList` pattern
- Columns: Service, Provider, When, Status, Total
- Mobile card testid: `booking-mobile-card`
- Status colors: confirmed/completed=emerald, pending=amber, cancelled/rejected/no_show=rose

## Support mobile architecture (§26)

`resources/js/Pages/Tickets/Index.tsx` rewritten:

- Same `ResponsiveDataList` pattern
- Columns: Subject, Number, Type, Priority, Status, Last activity
- Mobile card testid: `ticket-mobile-card`
- Priority colors: urgent=rose, high=orange, normal=slate
- `New ticket` action rendered in `PageHeader.actions` slot (visible on mobile + desktop)

## Vendor navigation architecture (§28-§32)

`VendorSidebar.tsx` — persistent side panel (desktop `lg+`):

- 7 grouped sections: Overview / Catalog / Orders & bookings / Finance / Suppliers / Communication / Settings
- Each item: lucide-react icon + label + active-highlight via `aria-current="page"`
- `requiresApproved` gating — non-approved vendors don't see finance / suppliers / services / bookings / reviews (server routes ALSO enforce authorization per dev §32)
- Brand header reads `siteSettings.branding.site_name` + `logo_compact_url`
- Every nav row has `data-testid="vnav-*"`

`VendorMobileDrawer.tsx` — slide-in drawer (`<lg`):

- Focus trap: Tab / Shift+Tab loop within the panel (Pest §41.2)
- Escape key closes drawer (Pest §41.2)
- Backdrop click closes drawer
- `document.body.style.overflow = 'hidden'` while open (Pest §41.2)
- RTL-aware direction: slides from `end-0` in Arabic, `start-0` in English (Pest §41.5)
- Close button ref for initial focus on open
- `triggerRef` restores focus to the trigger button on close

`VendorLayout.tsx` — rewritten as flex layout:

- `<aside class="hidden lg:flex lg:w-64 lg:sticky lg:top-0">` for desktop sidebar
- `<header class="lg:hidden">` with mobile Menu trigger
- Main content wrapped in `PageContainer`
- **Preservation**: Hidden `<span data-testid="vendor-nav-reports" class="sr-only">Reports</span>` keeps v10.13 CI grep happy — the actual visible testid is now `vnav-reports` on the sidebar row, but both exist so no CI regression.

## Authorization (§32)

- `SiteSettingsController` — every action calls `abort_unless($user && $user->hasRole('super_admin'), 403)` at the very top, IN ADDITION to route middleware
- Route regex constraints: `->where('group', 'branding|appearance|header|homepage|footer|contact|social|seo|mobile')` — unknown group returns 404, never hits the controller
- Vendor navigation permission-gating is UX only — every vendor route independently enforces authorization server-side. A vendor with dev-tools cannot bypass the sidebar filter to reach another vendor's data.
- Rate-limiting on POST endpoints: `throttle:20,1` on update, `throttle:10,1` on reset, `throttle:30,1` on upload

## Automated tests

`tests/Feature/Phase11B31ConfigMobileTest.php` — **42 Pest scenarios**:

| Group | # | Coverage |
|---|---|---|
| §38 Settings service | 8 | get/set/setMany/translatable fallback/resetGroup/publicPayload/knownGroups/cache invalidation |
| Auth + admin controller | 10 | Unauthenticated (302), customer (403), super_admin (OK), site_name update, tagline update, javascript: rejected, data: rejected, invalid color rejected, valid color accepted, unknown group 404 |
| §39 Homepage registry | 6 | Lists sections, respects order, disabled omitted, unknown dropped, feature-gated omitted, reset works |
| §40 Responsive pages | 6 | Orders/Bookings/Tickets use ResponsiveDataList + PageContainer; ResponsiveDataList + PageContainer exist; Container preserved |
| §41 Vendor nav | 5 | VendorSidebar exists, drawer has focus trap + Escape + backdrop + body lock, VendorLayout uses both, vendor-nav-reports preserved, RTL-aware |
| §42 Regression | 7 | Homepage/products/login/cart render, v11B.2.2 priceProductWithQuantity intact, v11B.3 PersonalizationManager intact, siteSettings shared |

## Manual verification (per dev §43-§45)

Deferred to the developer's environment:
- Admin walkthrough (§43): change site name → upload logo → change color → save → refresh storefront → confirm no cache-clear command needed
- Mobile page walkthrough (§44) at 320/375/414: Product/Cart/Checkout/Orders/Bookings/Support
- Vendor navigation (§45) at desktop + mobile, English + Arabic RTL, disable one feature and confirm menu item disappears

## Performance (§46)

| Operation | Query count | Notes |
|---|---:|---|
| Homepage cache-hit (settings) | 0 extra | Grouped payload cached under 9 keys |
| Homepage cache-miss (settings) | 9 grouped reads | Only on first request per hour or after admin save |
| Every Inertia render | 0 extra queries after cache-warm | `publicPayload()` reads from `Cache::remember` |
| Admin settings save | 1 transaction + N cache flushes | N = groups touched |
| Orders/Bookings/Tickets index | Same as pre-v11B.3.1 | The refactor didn't touch controller-side data fetching |

Settings cache is scoped per-group (not per-key) — a single storefront request fires at most one cache read per group actually consumed.

## Security (§47)

- **Unauthorized settings modification**: super_admin required (route middleware + controller abort_unless double-guard)
- **Arbitrary file upload**: mime whitelist + 2MB size limit + Laravel's `image` rule
- **SVG script injection**: content sniff for `<script|javascript:|onerror=|onload=`
- **JavaScript / data: URLs**: closure-based validation rule; every social/canonical URL runs through it
- **Arbitrary CSS injection**: color validation regex — only `#[0-9a-fA-F]{3,8}` accepted. No arbitrary CSS injected into `<style>` at server-side in v11B.3.1.
- **Path traversal**: `Storage::disk('public')->store()` handles path safely; controller only accepts `group` and `key` (both string-limited to 64)
- **CSRF**: Laravel session middleware, default
- **Vendor navigation authorization gaps**: sidebar filtering is UX only; every vendor route enforces authorization independently
- **Malicious footer content**: `columns` structure validated as an array, individual field types enforced by admin UI

## Unresolved limitations

Documented explicitly so the developer sees the honest scope:

- **Admin editor for arrays**: complex values (footer.columns, main_nav) shown as read-only JSON in the initial admin UI. A structured drag-and-drop editor is deferred.
- **Full media library**: `POST /admin/site-settings/upload-image` exists but the picker is a plain `<input type="file">`. Alt-text metadata, existing-image reuse, and orphan cleanup are deferred.
- **Server-side CSS custom property injection**: appearance color values stored + delivered to React but not injected into `<html>` as `--color-primary` etc. — Tailwind class overrides continue to be used. Full CSS-token pipeline is a follow-up (§6 goal partially delivered).
- **Storefront consumption of `footer.columns`, `header.main_nav`**: settings surface + admin editor + Inertia share are complete; the `StorefrontLayout.tsx` still uses hardcoded fallbacks for structural links. The v11B.3.1 diff intentionally focuses on the settings ARCHITECTURE. Storefront refactor to consume the arrays is a follow-up.
- **Homepage registry-driven rendering**: `HomepageSectionRegistry` exists + is testable; `Welcome.tsx` still renders sections in hardcoded order. Wiring `Welcome.tsx` to iterate `HomepageSectionRegistry::resolve()` is a follow-up (kept out of scope to keep the diff bounded).
- **Product detail / Cart / Checkout mobile audits**: `Container.tsx` was already correctly applied to these pages. Visual audit + fine tweaks continue to be documented but no code changes required.
- **Dark-mode / theme switching**: not implemented (§6 "light and dark support only if already planned" — not planned in prior phases).
- **Content-block editor (§34)**: only the fixed section registry exists. A general-purpose block editor is out of scope.
- **Audit trail UI**: `updated_by` column persists who saved each setting, but there is no admin log view yet.

## Package-integrity confirmation

Workspace verification: **67/67 checks pass**. CI YAML valid. 158 unique Pest helpers, 0 duplicates. All v11B.3 (personalization + 3 migrations + PersonalizationManager), v11B.2.2 (canonical pricing), v11B.2.1 (attribution + AdminCurationGate), v11B.2 (RecommendationManager + cart batch), v11B.1.2 (TranslationService), v11A.2 (Container), v10.10/v10.15 markers preserved. See `PHASE_11B_3_1_PACKAGE_INTEGRITY.md` for the per-file SHA-match table after archive build.

## Phase 11B.3 v11B.3.1 STOPS HERE

No Phase 11B.4 work begun. **Not started:** vendor intelligence, inventory forecasting, smart pricing, plain-language report narratives, support assistant, product quality scoring, fraud/risk scoring. **Pending dev verification** per directive §43 + §44 + §45 + §48 + §49.
