# Phase 11B.1 v11B.1.1 — Arabic Product Search and Mobile Suggestions Repair

Per dev §22.

## Scope statement

Two confirmed defects from v11B.1 testing. Surgical fixes only. No redesign. No changes to working search/filter/cart/checkout/authentication/reports/localization beyond the minimum needed to resolve the two issues.

## Defect 1 — Arabic product search did not work because no products had Arabic content

### Root cause

The v11B.1 audit was correct that the multilingual *infrastructure* shipped earlier:

- `products.name_translations` JSON column (from Phase 3)
- `products.description_translations` JSON column (from Phase 3)
- `Product::translatedName()` accessor (from Phase 3, wired into 5 controllers in v11A.5)
- v11B.1 search service already matched `name_translations.ar` via `JSON_EXTRACT`

But the **content layer was incomplete**:

1. **No `short_description_translations` column** — only `description_translations` existed. Catalog cards have nothing to localize.
2. **No vendor/admin UI** to enter Arabic content. Vendors could not populate `name_translations` or `description_translations` at all.
3. **No `translatedShortDescription()` / `translatedDescription()` accessors** on the Product model — they existed only for `name`.
4. **Catalog/Show.tsx** passed `$product->short_description` and `$product->description` (raw English columns), never the translated versions.
5. **MarketplaceSearchService description scoring** only checked `products.short_description` (LIKE), not the Arabic JSON path.
6. **No seeded Arabic content** for demo products — making Arabic search un-testable end-to-end.

So Arabic search "didn't work" because **no products had Arabic content to find**, not because the search algorithm was broken.

### Final multilingual product architecture

This release **extends** the existing JSON-translation pattern from v11A.5 (consistent architecture — no second competing translation system per dev §2).

```
products table
├── name                              (English required, existing)
├── name_translations          JSON   (existing, v11A.5 backfilled categories)
├── short_description                 (English nullable, existing)
├── short_description_translations  JSON  (v11B.1.1 NEW — added by 2026_06_27_000001)
├── description                       (English nullable, existing)
└── description_translations    JSON   (existing, never previously surfaced in UI)
```

Each JSON column is a `{ locale → text }` map (`{ "ar": "...", "ur": "..." }`). Empty/missing entries trigger English fallback via the accessor methods.

### Migrations added

| Migration | Operation | Reversibility |
|---|---|---|
| `2026_06_27_000001_add_short_description_translations_to_products.php` | `ALTER TABLE products ADD COLUMN short_description_translations JSON NULL AFTER short_description` | `down()` drops the column; idempotent via `Schema::hasColumn` guard |

No other migrations. **No existing data is modified.** Slugs, vendor_id, category_id, prices, status — all untouched.

### Model accessors added

- `Product::translatedShortDescription(?string $locale = null): ?string` — Arabic when locale=ar and value non-empty, else English `short_description`
- `Product::translatedDescription(?string $locale = null): ?string` — same contract for full description
- `Product::translationStatus(string $locale = 'ar'): array` — returns `['name' => bool, 'short_description' => bool, 'description' => bool]` for the Edit form's completeness badge

All accessors follow the v11A.5 `translatedName()` controlled-fallback pattern: `?? $this->{english_column}`.

### Forms updated

**Vendor product Create.tsx + Edit.tsx**: added three Arabic input fields next to each English field:

| Field | Direction | `lang` | Testid |
|---|---|---|---|
| Product name (Arabic) | `dir="rtl"` | `lang="ar"` | `vendor-product-name-ar` |
| Short description (Arabic) | `dir="rtl"` | `lang="ar"` | `vendor-product-short-desc-ar` |
| Full description (Arabic) | `dir="rtl"` | `lang="ar"` | `vendor-product-desc-ar` |

Edit.tsx also shows a `vendor-product-translation-status` panel with ✅/⚠️ indicators per Arabic field — at-a-glance completeness check. Arabic input is **optional**. English fields remain required. The form posts flat `name_ar` / `short_description_ar` / `description_ar` strings; the controller's private `foldTranslationFields()` helper folds them into the JSON columns, **preserving any pre-existing non-Arabic locale keys**.

**Filament admin ProductResource**: added a collapsed "Arabic translations (optional)" section with three fields written to dot-keyed JSON paths (`name_translations.ar`, `short_description_translations.ar`, `description_translations.ar`). Filament's `array` cast handles the round-trip automatically. `extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])` gives RTL input direction.

### Seed/test Arabic products

`Database\Seeders\ArabicProductContentSeeder` — idempotent backfill for 4 representative demo products:

| Slug | English name | Arabic name |
|---|---|---|
| `wireless-bluetooth-headphones` | Wireless Bluetooth Headphones | سماعات لاسلكية بتقنية البلوتوث |
| `cotton-t-shirt-classic-fit` | Cotton T-Shirt — Classic Fit | قميص قطني — قصة كلاسيكية |
| `stainless-steel-water-bottle` | Stainless Steel Water Bottle | زجاجة ماء من الفولاذ المقاوم للصدأ |
| `handwoven-beach-towel` | Handwoven Beach Towel | منشفة شاطئ منسوجة يدويًا |

Each product also gets Arabic short_description and Arabic description content. **Modern Standard Arabic** throughout. Each field is gated by `empty($existing['ar'])` so admin/vendor edits are preserved on re-run. Uses `saveQuietly()` — no events, no notifications. Wired into `DatabaseSeeder::run()` after `DemoSeeder` so the slugs exist.

Per dev §5 — "do not present seed data as a translation of all existing marketplace products": only 4 known demo slugs are touched. Production vendor products that don't match these slugs receive no Arabic.

### Fallback behavior (controlled, never invented)

Per dev §3 — "Do not automatically invent Arabic translations for vendor content":

| Locale | name_translations.ar | What's displayed |
|---|---|---|
| en | (any) | `products.name` (English) |
| ar | non-empty string | `name_translations['ar']` |
| ar | null / empty / missing key | `products.name` (English fallback) — **never invented** |

Same contract applies to `short_description` and `description`. The fallback is deterministic and admin-visible (via `translations:audit ar`).

### Search-query changes

`MarketplaceSearchService::products()` already scored `JSON_UNQUOTE(JSON_EXTRACT(products.name_translations, '$.ar'))` in v11B.1. v11B.1.1 extends:

1. **Score expression**: adds an additional score component for Arabic short_description matches when locale=ar (same weight as English description, `description_match = 8`).
2. **Eligibility WHERE**: extends the text-relevance gate so a product whose ONLY Arabic match is in `short_description_translations.ar` still qualifies for the result set.

Total Arabic JSON-extract sites in the search service: 3 (title score, title eligibility, short_description for both).

### Arabic relevance behavior

Unchanged from v11B.1: text relevance > popularity. An Arabic title match (`arabic_title_match = 40`) still outweighs the sum of all popularity + freshness + promotion + in-stock boosts. Arabic description-only matches qualify with `description_match = 8` — below all title matches, above no-match (excluded).

---

## Defect 2 — Mobile suggestions did not appear because mobile used a plain `<input>` instead of `<SearchBar>`

### Root cause

`StorefrontLayout.tsx` had **two separate search surfaces**:

| Surface | Component | Live suggestions? |
|---|---|---|
| Desktop header (`hidden md:flex`) | `<SearchBar variant="desktop" />` | ✅ Yes |
| Mobile drawer (`md:hidden ... aside`) | Plain `<form onSubmit={handleSearch}><input type="search" .../></form>` | ❌ **No — just GET /products?q=… on submit** |

The mobile drawer's search box was a relic from before v11A.4 introduced the SearchBar component. On mobile, typing characters dispatched no XHR — the `/search/suggestions` endpoint was never called. Pressing Enter performed a regular full-page navigation to the catalog.

Per dev §10 diagnostic checklist: **"mobile uses a different search component without suggestions"** is the exact root cause.

### Active mobile component hierarchy

```
StorefrontLayout
└── <header>
    ├── Desktop band (hidden md:flex)
    │   └── <SearchBar variant="desktop" />          ← had suggestions
    └── Mobile band (md:hidden flex)
        └── Hamburger button → setMobileOpen(true)

└── <aside id="mobile-drawer" md:hidden fixed inset-y-0 end-0 z-50>
    ├── Drawer header (close button)
    ├── Mobile search (THE DEFECT SITE) ────────────  ← was plain <input>
    └── Mobile nav links + collapsible categories
```

### z-index / overflow / responsive fix

| Concern | Mobile drawer | SearchBar dropdown panel |
|---|---|---|
| z-index | `z-50` (drawer) | `z-50` (dropdown) — same stacking context inside the drawer |
| Overflow | `overflow-y-auto` on drawer | Dropdown is `absolute inset-x-0 top-full mt-2` → renders **inside the drawer's scroll area**, fits the drawer width |
| Responsive hiding | `md:hidden` on drawer outer | Dropdown has **no responsive-hiding classes** — verified by §18.32 Pest assertion |
| Width | Drawer `w-[85%] max-w-sm` | Dropdown inherits via `inset-x-0` — full drawer width |
| Touch | Drawer is touch-scrollable | Dropdown items have `cursor-pointer` + `min-h` from existing v11A.4 styling |
| Escape close | Drawer keeps `setMobileOpen` | Dropdown's existing Escape handler closes the suggestions panel |

### Single reusable component (per dev §11)

The fix uses the **same `<SearchBar />` component** for desktop and mobile, differing only by the `variant` prop:

- `variant="desktop"` — shows the inline "Search" button (`absolute end-1.5`); height `h-11`
- `variant="mobile"` — hides the inline button (more touch room); height `h-12`

All the suggestion logic, debounce (`DEBOUNCE_MS = 300`, within dev's 250–350 ms range), `AbortController` stale-request cancellation, keyboard navigation, Escape/click-outside close, listbox/option ARIA semantics, RTL via logical CSS — **all reused identically**. Zero duplicated autocomplete code.

### Code change

`resources/js/Layouts/StorefrontLayout.tsx`:
- Replaced the plain `<form onSubmit={handleSearch}>...<input type="search" .../>...</form>` block (12 lines) with `<div data-testid="mobile-drawer-search"><SearchBar variant="mobile" /></div>` (3 lines)
- Removed the now-dead `const [searchQuery, setSearchQuery]` state
- Removed the now-dead `const handleSearch = (e: FormEvent) => { ... }` handler
- Removed the unused `FormEvent` import

Net delta on StorefrontLayout.tsx: **−17 lines**.

---

## Automated tests

`tests/Feature/Phase11B11ArabicMobileTest.php` — 46 Pest scenarios per dev §18:

| Group | Count | Coverage |
|---|---|---|
| §18.1-8 Arabic product content | 8 | Save Arabic name/short/full, English-only remains valid, Arabic doesn't overwrite English, vendor can edit via POST, translationStatus accurate, unauthenticated cannot edit |
| §18.9-14 Arabic display | 6 | Arabic locale shows Arabic title, English locale shows English, missing Arabic falls back, product detail shows Arabic description, product card localized, null-when-both-absent |
| §18.15-23 Arabic search | 9 | Exact/prefix/partial Arabic title; Arabic description match at lower weight; English still works; Arabic category still works; Arabic service surface reachable; hidden excluded; suspended-vendor excluded |
| §18.24-28 Suggestions | 5 | Arabic product appears; Arabic label displays (not English); English fallback; result limit respected; standard search works with suggestions disabled |
| §18.29-38 Mobile suggestions | 10 | SearchBar in drawer; no plain input remains; endpoint reachable from any UA; no md:hidden hiding the panel; variant=mobile supported; Escape+click-outside handlers; AbortController stale-request protection; same endpoint as desktop; mobile-drawer-search testid; desktop suggestions remain |
| §18.39-46 Regression | 8 | Customer login, guest homepage, cart, checkout, admin reports, vendor reports, Arabic locale persists, TypeScript contract |
| **Total** | **46** | |

CI sub-check count: **122** (112 v11B.1 + 10 new v11B.1.1).

Total Pest scenarios across all suites: **505** (459 v11B.1 + 46 v11B.1.1).

Unique global helpers: **123** (118 v11B.1 + 5 p11b11_*), 0 duplicates.

---

## Manual tests

Per dev §19 — to be performed by the developer:

### Arabic products (10 steps)

1. Run `php artisan migrate` → applies the additive `short_description_translations` migration
2. Run `php artisan db:seed --class=ArabicProductContentSeeder` → backfills 4 demo products
3. Log in as `vendor@marketplace.test`, navigate to `/vendor/products/{id}/edit`
4. Confirm 3 Arabic fields appear: name_ar, short_description_ar, description_ar
5. Confirm `dir="rtl"` + `lang="ar"` on each Arabic input
6. Type Arabic text in each field → save
7. Confirm translation_status panel updates from ⚠️ to ✅
8. Switch language to العربية on the storefront → search "حاسوب" → confirm Arabic product appears
9. Open the product detail page → confirm Arabic title + description display
10. Switch back to English → confirm English title returns (controlled fallback intact)

### Mobile suggestions (per width — 320 / 375 / 390 / 414px)

1. Open mobile viewport, tap hamburger → drawer opens
2. Tap the search input
3. Type 2+ characters (English or Arabic)
4. **Confirm `/search/suggestions?q=...` XHR fires** in DevTools Network panel
5. Confirm dropdown appears below the input, within the drawer
6. Tap a suggestion → confirm navigation
7. Re-open drawer, type again, press Escape → confirm dropdown closes
8. Re-open drawer, type, tap outside the input → confirm dropdown closes
9. Confirm no horizontal scroll on the page
10. Switch to Arabic locale → repeat 2-9 with Arabic input

### Desktop regression

1. Confirm desktop search bar still works at ≥ md breakpoint
2. Test in both English and Arabic
3. Confirm popular + recent groups still appear on focus

---

## Performance results

I cannot run live profiling in this sandbox. Design analysis:

| Operation | Query cost | N+1 risk |
|---|---|---|
| Arabic title search | 1 query — same SQL pass as v11B.1, additional Arabic JSON-extract clause in CASE expression | NO |
| Arabic short_description search | 1 query — additional CASE component in score; eligibility-WHERE handles by another OR clause | NO |
| Mobile suggestions | Identical to desktop — same endpoint, same SearchBar component | NO |
| Product detail (Catalog/Show) | Same query plan as v11B.1; controller swap to `translatedShortDescription()` is in-memory accessor | NO |
| Vendor product Edit page | Same query as before + one in-memory `translationStatus()` call (no extra DB hit) | NO |
| Filament admin Arabic write | Filament's dot-keyed paths use the existing `array` casts — no extra ALTERs or migrations | NO |

The single additive migration (`short_description_translations`) is a JSON column add; MySQL adds JSON columns without rewriting existing rows.

---

## Query-plan evidence

To be captured by dev per §17 + §20. Recommended EXPLAIN commands:

```sql
-- Arabic title query (with the v11B.1 weighted scoring)
EXPLAIN SELECT products.* FROM products
WHERE LOWER(JSON_UNQUOTE(JSON_EXTRACT(name_translations, '$.ar'))) LIKE '%حاسوب%'
  AND status = 'published';

-- Expected: uses products_status_published_at index for the status filter;
-- JSON_EXTRACT is computed per row (not a candidate for index in MySQL 8 unless
-- a generated virtual column + index is added — deferred to v11B.x if needed).
```

No indexes added in v11B.1.1 — adding an index on a JSON-extracted value requires a generated virtual column (MySQL 5.7+) and is a separate performance-tuning concern that should be measured first. **Per dev §17 — "Do not claim performance improvement without query-plan evidence"** — left for the dev to measure against real data volume.

---

## Remaining limitations / honest gaps

- **Generated virtual column index on `name_translations.ar`** — not added; would require an additional migration; deferred until live perf measurement justifies it
- **`meta_title` / `meta_description` translations** — out of scope for v11B.1.1 (dev §3 lists them as "where applicable"); their SEO impact for Arabic users is real but secondary to product content
- **Service product Arabic content** — services use `type='service'` on the products table, so they inherit the new columns automatically; the **service-detail page** (`/services/{slug}`) shows English description in some places — full service Arabic display is out of v11B.1.1 scope per dev §9 "recheck the complete multilingual search path" addresses search, not display surfaces beyond product detail
- **Tags / brands localization** — there's no tags or brands table in the schema yet; translation deferred to when those features exist
- **Cart line items** — the cart shows `Product::translatedName()` already (since v11A.5); cart line `short_description` is not displayed in any view, so v11B.1.1 didn't need to change cart
- **Admin "audit Arabic completeness" dashboard** — `php artisan translations:audit ar` already lists products missing Arabic; a Filament dashboard widget is out of scope

---

## Package-integrity confirmation

Per dev §23:

- ✅ Migration included (`2026_06_27_000001_add_short_description_translations_to_products.php`)
- ✅ Model accessor changes included (3 new methods + 1 fillable + 1 cast)
- ✅ Vendor + admin forms included (Create.tsx, Edit.tsx, Filament/ProductResource.php)
- ✅ Arabic seed/test method included (ArabicProductContentSeeder.php + DatabaseSeeder wiring)
- ✅ Search query changes included (MarketplaceSearchService 3 Arabic JSON sites)
- ✅ Mobile suggestion fix included (StorefrontLayout.tsx SearchBar swap)
- ✅ 46 Pest scenarios included
- ✅ No obsolete nested project packaged (verified via tarball leak check)

---

## Files changed in v11B.1.1

| File | Type | Notes |
|---|---|---|
| `database/migrations/2026_06_27_000001_add_short_description_translations_to_products.php` | **NEW** | Additive JSON column, idempotent |
| `database/seeders/ArabicProductContentSeeder.php` | **NEW** | Idempotent Arabic backfill, 4 demo products |
| `database/seeders/DatabaseSeeder.php` | MODIFIED | +1 `->call(ArabicProductContentSeeder::class)` |
| `app/Models/Product.php` | MODIFIED | +3 accessors + 1 fillable entry + 1 cast |
| `app/Http/Controllers/Vendor/VendorProductController.php` | MODIFIED | +3 validation rules ×2 (store + update); +1 helper `foldTranslationFields`; edit() surfaces flat fields + translation_status |
| `app/Http/Controllers/CatalogController.php` | MODIFIED | show() uses translatedShortDescription() + translatedDescription() |
| `app/Services/Search/MarketplaceSearchService.php` | MODIFIED | +1 Arabic short_description score component; +1 Arabic short_description eligibility OR clause |
| `app/Filament/Resources/ProductResource.php` | MODIFIED | +1 Arabic translations section with 3 dot-keyed fields + RTL |
| `resources/js/Pages/Vendor/Products/Create.tsx` | MODIFIED | +3 Arabic input fields + Form type + initial state |
| `resources/js/Pages/Vendor/Products/Edit.tsx` | MODIFIED | +3 Arabic input fields + translation_status panel + Props/Form types |
| `resources/js/Layouts/StorefrontLayout.tsx` | MODIFIED | Mobile drawer plain input → `<SearchBar variant="mobile" />`; removed dead state |
| `lang/en.json` | MODIFIED | 352 → 361 keys (+9 vendor form labels) |
| `lang/ar.json` | MODIFIED | 352 → 361 keys (+9 Modern Standard Arabic) |
| `tests/Feature/Phase11B11ArabicMobileTest.php` | **NEW** | 46 Pest scenarios |
| `.github/workflows/ci.yml` | MODIFIED | +10 v11B.1.1 sub-checks + Pest filter |
| `VERSION` | `Phase 11B.1` → `Phase 11B.1 v11B.1.1` |

## Counts

| Metric | v11B.1 → v11B.1.1 |
|---|---|
| CI sub-checks | 112 → **122** (+10) |
| Pest scenarios | 459 → **505** (+46) |
| Unique Pest helpers | 118 → **123** (+5 p11b11_*) |
| Translation keys (en/ar each) | 352 → **361** (+9) |
| New migrations | — → **1** |
| New seeders | — → **1** |
| New PHP files | — → **3** (migration + seeder + Pest test) |
| Product model accessors | 1 (translatedName) → **4** (+3) |

## Phase 11B.1 v11B.1.1 STOPS HERE

No Phase 11B.2 work begun. Pending dev verification per §19 manual walkthrough + §21 commands + §17 query-plan capture against real data.
