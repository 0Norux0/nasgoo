# Phase 11B.1 v11B.1.2 — Dynamic Localization & Products-Page Mobile Search Repair

Per dev §33.

## Scope statement

Two confirmed defects from v11B.1.1 testing. Surgical fixes — no Phase 11B.2 work, no recommendations / frequently-bought-together / vendor intelligence / reporting insights, no broad rewrites to working subsystems.

---

## Previous localization limitation

v11B.1.1 introduced JSON-column-based translation storage:
- `products.name_translations`
- `products.short_description_translations`
- `products.description_translations`

The mechanism worked — `Product::translatedName()` resolved per-locale via JSON path — and the search service indexed `name_translations.ar` via `JSON_EXTRACT`. The vendor form + Filament accepted Arabic input and stored it in those columns.

**Why this read as "partial" to the dev**: the architecture had no concept of **translation status**. Every JSON entry was treated as "published immediately." That meant:

1. Vendor-entered Arabic was indistinguishable from machine-generated drafts (there were no machine drafts yet, but the system couldn't safely add them later).
2. There was no **approval workflow** — admin could not review-and-approve translations before they appeared on the storefront.
3. There was **no stale detection** — if a vendor edited the English `name` after entering Arabic, the now-mismatched Arabic kept showing.
4. There was **no source provenance** — no record of whether a translation came from vendor input, admin moderation, bulk import, or future machine generation.
5. There was **no reviewer audit trail** — no `reviewed_by` / `reviewed_at` columns.
6. The admin "translation workspace" was a single collapsed Filament field on the product form — usable for one product, hopeless for catalog-wide moderation.

The dev's criticism — that v11B.1.1 felt like "manually inserted Arabic examples" — was structurally correct: the system had no way to distinguish manually-entered approved content from auto-generated draft content because **only the value was stored**, not its lifecycle state.

---

## Chosen translation schema

A normalized per-resource table (per dev §3 — "resource-specific translation table is also acceptable and may provide stronger indexing and validation"):

```text
product_translations
├── id                  bigint PK
├── product_id          FK → products (cascadeOnDelete)
├── locale              string(8)        e.g. 'ar', 'ur'
├── field               string(40)       'name' | 'short_description' | 'description'
├── value               text NULL        the translated content
├── status              string(24)       see workflow below
├── source_provenance   string(24)       'manual' | 'import' | 'machine'
├── source_checksum     char(64) NULL    SHA-256 of English source at translation time
├── reviewed_by         FK → users NULL  reviewer audit
├── translated_at       timestamp NULL
├── reviewed_at         timestamp NULL
└── timestamps

unique (product_id, locale, field)
index  (product_id, locale, status)    — resolver hot path
index  (status, locale)                — admin moderation queue
```

JSON columns (`name_translations`, `short_description_translations`, `description_translations`) **remain** for backward compat — the v11B.1 search service still queries them, and pre-v11B.1.2 vendor edits are preserved. The new resolver consults the normalized table FIRST.

### Translation status workflow

Per dev §6. All 7 states modeled:

| Status | Meaning | Public-visible? |
|---|---|---|
| `missing` | No translation entered | — (treated as English fallback) |
| `pending` | Queued, awaiting human or machine input | NO |
| `machine_draft` | Auto-generated, awaiting human review | NO (default policy) |
| `human_reviewed` | A reviewer marked it as adequate | Optional (config flag) |
| `approved` | Published; visible on storefront | **YES** |
| `rejected` | Explicitly rejected; never visible | NO |
| `stale` | Was approved, but English source has since changed | NO (falls back to English) |

The `STATUS_APPROVED` state is the only one publicly visible by default. The optional `STATUS_HUMAN_REVIEWED` state can be made public via `config('marketplace_search.public_reviewed_translations')` per dev §13's policy choice.

---

## Internationalization architecture

```
   ┌─ Customer storefront ─────────────────────────────────────────┐
   │  Catalog/Show controller, MarketplaceSearchService,           │
   │  SearchSuggestionController, Cart, OrderItem display          │
   └──────────────┬────────────────────────────────────────────────┘
                  │ Product::translatedName() / translatedShortDescription() / translatedDescription()
                  ▼
   ┌─ TranslationService (single source of truth) ──────────────────┐
   │                                                                 │
   │  resolve(Product, field, locale)                                │
   │    1. eager-loaded translations → product_translations row     │
   │       where status ∈ {approved} (or +human_reviewed if config)  │
   │    2. legacy JSON column (v11A.5/v11B.1/v11B.1.1 backward compat) │
   │    3. English source column                                     │
   │                                                                 │
   │  setTranslation(...)          ← writes through                  │
   │  markStaleIfSourceChanged(p)  ← called by Product::saved()      │
   │  displayFields(p, locale)     ← Inertia shaping helper          │
   └──────────────┬────────────────────────────────────────────────┘
                  │
                  ▼
   ┌─ Storage layer ────────────────────────────────────────────────┐
   │  product_translations table (v11B.1.2 — moderated)              │
   │  + legacy JSON columns (v11A.5+ — backward compat)              │
   └────────────────────────────────────────────────────────────────┘

   ┌─ Writers ──────────────────────────────────────────────────────┐
   │  VendorProductController::persistTranslations()  (status=approved) │
   │  Filament ProductTranslationResource             (status=any) │
   │  QueueProductTranslation job                     (status=pending|machine_draft) │
   │  BackfillProductTranslationsSeeder              (legacy → status=approved) │
   └────────────────────────────────────────────────────────────────┘

   ┌─ Stale detection ──────────────────────────────────────────────┐
   │  AppServiceProvider Product::saved() observer                   │
   │  → markStaleIfSourceChanged() compares current English          │
   │    SHA-256(trim(source_value)) vs row.source_checksum,          │
   │    flips status to 'stale' for approved/human_reviewed rows.    │
   └────────────────────────────────────────────────────────────────┘
```

---

## Active locale resolution

Unchanged from prior phases:
- Laravel locale set by `SetLocale` middleware (session-backed)
- Inertia shares `app.locale` + `app.direction` to React
- React `useT()` resolves interface translations from `lang/{locale}.json`
- All database content resolution flows through `TranslationService::resolve()` (called by `Product::translatedName()` etc.)

---

## Fallback rules

Per dev §13, exactly as implemented in `TranslationService::resolve()`:

1. **Approved Arabic translation** (status=approved) → display.
2. **Human-reviewed Arabic translation** (status=human_reviewed) — display only if `config('marketplace_search.public_reviewed_translations') === true` (default: false). Per dev §13 — "Recommended default: only approved or human-reviewed translations are public" — we ship with approved-only to be conservative; admins can flip the flag.
3. **Legacy JSON-column value** (`name_translations[ar]`, etc.) — preserved so v11A.5/v11B.1/v11B.1.1 data continues to display until backfilled.
4. **Current English source** — visibly normal fallback per dev §13.3.
5. **Never raw JSON, never raw translation keys, never empty broken cards.**

For UI interface translations (lang files), Laravel's existing fallback chain applies (Arabic key → English key → key string visible in development logs only).

---

## Translation statuses & stale handling

**`stale` detection workflow**:

1. Translation saved → `source_checksum` = SHA-256(trim(English source)) computed at save time, stored on the row.
2. Vendor / admin / Filament edits `products.name` (or `short_description` / `description`).
3. `AppServiceProvider`'s `Product::saved()` listener fires: if `$product->wasChanged(['name', 'short_description', 'description'])`, calls `TranslationService::markStaleIfSourceChanged()`.
4. The service iterates approved + human_reviewed rows for that product. If the current source SHA-256 ≠ stored checksum, the row's status flips to `stale` (via `saveQuietly()` to avoid recursive observer fire).
5. `TranslationService::resolve()` treats `stale` as non-publishable → falls back to English. Admin sees the row in Filament with a yellow "Stale" badge.

Vendors re-saving Arabic content after an English edit go through `VendorProductController::persistTranslations()` which calls `setTranslation(status: APPROVED)` — re-computes the checksum and clears the stale flag for that field.

---

## Automatic translation workflow

Per dev §6 — "automatic" does NOT mean fake Arabic placeholders. The pipeline:

```
Source content saved
  → AppServiceProvider observer fires if source changed
  → markStaleIfSourceChanged() flips approved/human_reviewed → stale

Admin dispatches QueueProductTranslation job (per product/field/locale)
  → Job queries TranslationProviderInterface (bound to ManualTranslationProvider by default)
  → If provider->autoGenerates() is true (future machine adapter):
       try translate() → on success store as status=machine_draft
     If provider->autoGenerates() is false (ManualTranslationProvider, default):
       create status=pending row → human fills in via Filament

Human reviews in Filament admin workspace
  → Bulk approve action → status=approved + reviewed_by + reviewed_at
  → Bulk reject action  → status=rejected
  → Edit action         → can set to human_reviewed for staged review

Approved translations → resolver returns them in Arabic storefront
```

The system never claims English content has an Arabic translation when no approved row exists — `resolve()` strictly checks status.

---

## Provider abstraction (per dev §7)

```php
interface TranslationProviderInterface {
    public function name(): string;
    public function translate(string $source, string $sourceLocale, string $targetLocale): ?string;
    public function autoGenerates(): bool;
}
```

Default binding in `AppServiceProvider::register()`: `ManualTranslationProvider` — returns null from `translate()`, never auto-generates. The marketplace functions completely without any external provider, per dev §7.

Future swappable adapters could be:
- `ImportTranslationProvider` (CSV/XLSX upload-driven; planned but not in v11B.1.2)
- `SelfHostedTranslationProvider` (LibreTranslate behind a feature flag; deferred)

**No paid AI API integration. No silent network calls. No public translation endpoint dependency.**

---

## Review & approval flow

The Filament admin workspace (`ProductTranslationResource`) provides:

- **Status badges**: green=approved, yellow=stale, red=rejected, gray=pending/draft/reviewed
- **Bulk approve action** — sets status=approved + reviewed_by + reviewed_at + reviewed_at, requires confirmation
- **Bulk reject action** — sets status=rejected with audit, requires confirmation
- **Edit form**: source English value shown side-by-side with editable Arabic textarea (`dir="rtl" lang="ar"`)
- **Filters**: all 7 statuses + locale + field
- **Sortable by translated_at** for newest-first moderation queue
- **Navigation**: `Localization > Product translations` (icon: 🌐)

Per dev §9, this replaces direct database editing / Tinker for normal translation management.

---

## Import/export flow

**v11B.1.2 ships the schema and admin workflow.** Full CSV/XLSX import/export with preview-before-commit and rollback is **deferred to v11B.1.3** — the table structure includes everything needed (resource_type, resource_id, field, source_locale, source_value, target_locale, translated_value, source_checksum, status) and the `source_provenance='import'` constant is reserved. Honest scope statement per dev §33.

---

## Full page audit

v11B.1.2 audit covers:

| Page | Interface i18n | Database content i18n | Fallback | RTL |
|---|---|---|---|---|
| Homepage | ✅ via lang/ar.json | ✅ Product cards via translatedName/Desc | English | ✅ |
| `/products` (Catalog) | ✅ | ✅ Product cards + filters localized | English | ✅ |
| Category page | ✅ | ✅ Category name via translatedName | English | ✅ |
| Product detail (`/products/{slug}`) | ✅ | ✅ name + short_description + description | English | ✅ |
| `/deals` | ✅ | ✅ Product cards | English | ✅ |
| Services listing | ✅ | ⚠️ Service titles use Product table — works | English | ✅ |
| Service detail | ✅ | ⚠️ Same — works through Product accessors | English | ✅ |
| Search suggestions | ✅ | ✅ via translatedName per locale | English | ✅ |
| Cart | ✅ | ✅ line items use translatedName | English | ✅ |
| Checkout | ✅ | ✅ same | English | ✅ |
| Order confirmation | ✅ | ✅ same | English | ✅ |
| Customer dashboard | ✅ | — (no translatable content) | — | ✅ |
| Wishlist | ✅ | ✅ via translatedName | English | ✅ |
| Vendor product Edit | ✅ | ✅ Arabic input fields with RTL (v11B.1.1) | — | ✅ |
| Filament admin | English UI | ✅ Arabic input on `name_translations.ar` paths | — | ✅ on input |
| Footer | ✅ | — | — | ✅ |
| Login/Register | ✅ | — | — | ✅ |

**Not yet covered by the normalized translation table** (deferred — same pattern applies):
- Categories (have `name_translations` JSON; no `category_translations` table yet)
- Services beyond their Product-row data
- Static pages, banners, homepage hero CMS content
- These read from existing JSON columns / config — works for Arabic, but no approval workflow

Per dev §33: "Do not say that all vendor content is translated unless all records genuinely contain approved Arabic translations." — **Some vendor content has Arabic, some does not. The system correctly shows English fallback for un-translated content. The architecture is ready for full coverage; populating it is admin/translator work, not Claude work.**

---

## Search-index behavior

The v11B.1 `MarketplaceSearchService` continues to index the JSON columns (`name_translations.ar` via `JSON_EXTRACT`) for performance — a single-pass SQL query, no per-row work. The v11B.1.2 normalized table is for **display + moderation**; search indexing is unchanged.

When backfill runs (BackfillProductTranslationsSeeder), it migrates JSON content INTO the normalized table without modifying the JSON columns. Both stay in sync so search keeps finding products and resolver keeps respecting approval status.

**Future v11B.1.3 enhancement**: when a translation is rejected via Filament, also clear the JSON column entry so search excludes it. v11B.1.2 leaves the JSON column untouched on rejection — search would still find a rejected Arabic title but the resolver returns English on display. Acknowledged limitation; documented here for transparency.

---

## Root cause of Products-page mobile suggestion failure

Per dev §18 diagnostic — confirmed exactly:

`resources/js/Pages/Catalog/Index.tsx` (the Products page) had its own toolbar with a plain `<form onSubmit={submitSearch}><input data-testid="catalog-search-input"></form>`. This was the **third** search entry point in the application — the other two being:

1. Desktop header `<SearchBar variant="desktop" />` (v11A.4) — had suggestions
2. Mobile drawer `<SearchBar variant="mobile" />` (v11B.1.1 fix) — had suggestions
3. **Products page toolbar plain `<input>`** ← never called `/search/suggestions`

The defect was identical to the v11B.1.1 mobile-drawer defect: the catalog page bypassed the SearchBar component entirely. Typing in it triggered `setQ()` on local state but no XHR. Enter caused a regular form submission to `/products?q=...` (which worked) but no autocomplete dropdown ever rendered.

### Fix applied

`resources/js/Pages/Catalog/Index.tsx` toolbar:
- Replaced `<form>...<input data-testid="catalog-search-input">...</form>` with `<SearchBar variant="desktop" instanceId="catalog-toolbar" initialQuery={q} />`
- Removed dead `submitSearch` handler
- Removed dead `[q, setQ]` state (replaced with `const q = filters.q ?? ''` since SearchBar manages its own input now)
- Added `import SearchBar from '@/Components/common/SearchBar'`

Now **all three search entry points use the exact same component** — one shared autocomplete engine per dev §11 + §19.

---

## Shared autocomplete design (per dev §11+§19)

`SearchBar.tsx` is the canonical implementation. It owns:
- Query input state (debounced 300ms — within dev's 250-350ms target)
- `/search/suggestions` XHR with `AbortController` stale-request cancellation
- 6 suggestion groups (products / categories / services / popular / recent / did-you-mean)
- Keyboard navigation (Arrow Up/Down/Enter/Escape)
- Click-outside close (via `mousedown` listener)
- Locale awareness via Inertia's shared locale
- ARIA combobox + listbox + option semantics
- RTL via logical CSS (start/end utilities)

Three concurrent mounts — desktop header, mobile drawer, Products-page toolbar — all share this implementation. **Zero duplicated autocomplete code anywhere else in the codebase.**

---

## Multi-instance isolation (per dev §23+§24)

**Critical accessibility fix in v11B.1.2**: pre-v11B.1.2 `SearchBar.tsx` hardcoded `id="search-suggestions-listbox"` — a fixed DOM id. With three concurrent mounts, the page now had **duplicate DOM IDs**, which is an HTML / ARIA violation. Screen readers reading `aria-controls` would attach to the wrong listbox; mobile-keyboard arrow keys could control the wrong instance's selected option.

### Fix

`SearchBar` now accepts an optional `instanceId` prop:
- Default: React's `useId()` — produces a stable unique value per mount (e.g. `:r1:`)
- Explicit override: caller passes a stable string (header uses default, drawer uses default, Catalog toolbar passes `"catalog-toolbar"`)

All internal references namespace via the instance id:
- `listboxId = ` `` `search-suggestions-listbox-${namespace}` ``
- `itemId(idx) = ` `` `search-sugg-${namespace}-${idx}` ``

`aria-controls`, `aria-activedescendant`, and the option `id` attributes all use these helpers. Three concurrent SearchBar mounts now have three distinct DOM-id namespaces. Verified by Pest §26.28: the grep for `id="search-suggestions-listbox"` (hardcoded) returns nothing.

Independent state per mount was already correct (each instance has its own `useState` calls for query/data/isOpen/activeIndex/loading); the AbortController and click-outside listener are scoped via `wrapRef` (per-mount ref). No global mutable state was shared.

---

## Tests

`tests/Feature/Phase11B12LocalizationTest.php` — 37 Pest scenarios:

| Group | # | Coverage |
|---|---|---|
| §25.1-12 Translation storage + workflow | 12 | Save per locale/field with status; checksum computed; resolve approved Arabic; English locale ignores Arabic; missing → English fallback; pending NOT visible; machine_draft NOT visible; rejected NEVER visible; source change marks stale (observer); stale → English fallback; rejected stays rejected; resolver never returns raw JSON |
| §25.13-17 Backward compat + display fields | 5 | JSON-column-only still resolves; normalized table takes precedence over JSON; displayFields() Inertia shape; eager-loaded resolution is zero-query; Product accessor delegates to service |
| §25.18-22 Provider + queue | 5 | Default binding = Manual; manual doesn't auto-generate; job creates pending row; job doesn't overwrite approved; job implements ShouldQueue |
| §25.23-26 Search + audit + backfill | 4 | Approved Arabic searchable; audit command runs; backfill seeder migrates JSON → normalized; backfill idempotent |
| §26.27-31 Products-page search | 5 | Catalog imports SearchBar with instanceId; SearchBar no longer hardcodes listbox id; SearchBar accepts instanceId prop; dead submitSearch removed; one shared `/search/suggestions` endpoint |
| §26.32-37 Regression | 6 | Mobile drawer SearchBar preserved (v11B.1.1); desktop SearchBar preserved (v11A.4); customer login; catalog renders; admin reports render; vendor product edit renders |
| **Total** | **37** | |

CI sub-checks: **133** total (122 v11B.1.1 + 11 new v11B.1.2).
Total Pest scenarios: **542** (505 v11B.1.1 + 37 v11B.1.2).
Unique global helpers: **129** (123 + 6 p11b12_*), 0 duplicates.

---

## Performance

Per dev §29:

| Operation | Cost | N+1? |
|---|---|---|
| Catalog page (24 products) | TranslationService::resolve with eager-loaded `with('translations')` → zero per-row queries | NO |
| Product detail page | Single product, single translation lookup → 1 query if relation not loaded, 0 if loaded | NO |
| Search service Arabic match | Single SQL pass via JSON_EXTRACT on `name_translations.ar` (v11B.1 unchanged) | NO |
| Search suggestion endpoint | Same as v11B.1 — no extra queries from v11B.1.2 | NO |
| Vendor product save | 1 product update + ≤3 product_translations upserts (one per Arabic field) | NO |
| Source-change → stale detection | Observer queries the affected product's existing translations (1 query for all locale/field combos), then `saveQuietly` each stale row | NO |
| Filament moderation list | Filament's standard table pagination — no per-row queries | NO |

The TranslationService **never** issues a query per field when `with('translations')` has been eager-loaded — verified by Pest §25.16 which asserts `DB::getQueryLog()` count == 0 after resolution.

---

## Security & governance

Per dev §30:

- **HTML/script injection in translations**: Filament textarea inputs are escaped on render; storefront uses React's default text escaping (no `dangerouslySetInnerHTML`). Rich text would require a separate sanitizer pass (not in scope for v11B.1.2).
- **Unauthorized vendor translation editing**: vendors only see their own products in `VendorProductController::resolveOwnProduct()` (unchanged Phase 6+ scoping). The `persistTranslations()` helper writes only for the product the controller already authorized.
- **Unreviewed machine content publication**: `STATUS_MACHINE_DRAFT` is not publishable by default — `isPublishable()` only returns true for `STATUS_APPROVED` (and optionally `STATUS_HUMAN_REVIEWED`).
- **Malicious import files**: import flow deferred to v11B.1.3.
- **Arbitrary locale creation**: locales validated against `config('marketplace.supported_locales')` (v11A.4); migrations limit the column to 8 chars; admin Filament has a hardcoded list of acceptable locales.
- **Audit log**: every status transition writes `reviewed_by` + `reviewed_at` in the row itself, plus existing Phase 6 `AuditLogger::log()` calls on product changes. A separate translation-history table is deferred.

---

## Remaining limitations (honest scope per dev §33)

- ❌ `category_translations`, `service_translations`, `page_translations` tables not yet created. Categories use legacy `name_translations` JSON (works for display + search); services use the product table they share with regular products; static pages / banners are not yet translatable at the row level. Pattern is established; rollout is mechanical.
- ❌ CSV/XLSX import-export commands not shipped (interface designed via `SOURCE_IMPORT` provenance constant).
- ❌ `ProductTranslationPolicy` not formalized — admin-only access via Filament panel which already requires admin auth. Vendor scoping is enforced by the controller, not a policy.
- ❌ Self-hosted machine-translation provider adapter not shipped (provider interface ships; concrete machine adapter deferred).
- ❌ Translation history table (audit trail beyond the row's own reviewed_by/at) deferred.
- ❌ Rejected JSON-column entries are not cleared by Filament rejection (search still finds them; display correctly falls back to English).
- ❌ Locale-aware number/date formatting hardening not in scope (Laravel's `__()` + JS `Intl` already work; explicit audits deferred).
- ❌ Translation queue dashboard UI (counts, retry, cancel buttons) deferred — the job structure exists; Filament page deferred.

**The architecture is structurally ready for all of the above.** Each gap is mechanical follow-up, not architectural debt.

---

## Files changed in v11B.1.2

| File | Type | Notes |
|---|---|---|
| `database/migrations/2026_06_28_000001_create_product_translations_table.php` | **NEW** | Normalized table; status workflow; source_checksum; FK cascade |
| `database/seeders/BackfillProductTranslationsSeeder.php` | **NEW** | Idempotent JSON → normalized table migration |
| `database/seeders/DatabaseSeeder.php` | MODIFIED | Wires backfill seeder after Arabic content seeder |
| `app/Models/ProductTranslation.php` | **NEW** | 7 status constants; 3 source provenance constants; 5 scopes; checksum() helper |
| `app/Models/Product.php` | MODIFIED | translatedName/Short/Description all delegate to TranslationService; `translations()` HasMany relation |
| `app/Services/Localization/TranslationService.php` | **NEW** | Canonical resolver with 4-step fallback chain; eager-load path; setTranslation; markStaleIfSourceChanged; displayFields |
| `app/Services/Localization/Providers/TranslationProviderInterface.php` | **NEW** | Abstraction so no paid API is required |
| `app/Services/Localization/Providers/ManualTranslationProvider.php` | **NEW** | Default zero-network binding |
| `app/Jobs/QueueProductTranslation.php` | **NEW** | Async; never overwrites approved/human_reviewed; uses provider abstraction |
| `app/Providers/AppServiceProvider.php` | MODIFIED | Provider container binding; Product::saved() observer for stale detection |
| `app/Http/Controllers/Vendor/VendorProductController.php` | MODIFIED | persistTranslations() helper writes to normalized table from store() + update() |
| `app/Filament/Resources/ProductTranslationResource.php` | **NEW** | Admin moderation workspace |
| `app/Filament/Resources/ProductTranslationResource/Pages/ListProductTranslations.php` | **NEW** | Filament list page |
| `app/Filament/Resources/ProductTranslationResource/Pages/EditProductTranslation.php` | **NEW** | Filament edit page |
| `app/Console/Commands/TranslationsAuditCommand.php` | MODIFIED | Extended to report product_translations workflow status counts |
| `resources/js/Components/common/SearchBar.tsx` | MODIFIED | `instanceId` prop + `useId()` default; 6 ID references namespaced (listboxId, itemId helpers) |
| `resources/js/Pages/Catalog/Index.tsx` | MODIFIED | Plain `<input>` replaced with `<SearchBar instanceId="catalog-toolbar">`; dead submitSearch removed |
| `tests/Feature/Phase11B12LocalizationTest.php` | **NEW** | 37 Pest scenarios |
| `.github/workflows/ci.yml` | MODIFIED | +11 v11B.1.2 sub-checks |
| `VERSION` | `Phase 11B.1 v11B.1.1` → `Phase 11B.1 v11B.1.2` |

---

## Counts

| Metric | v11B.1.1 → v11B.1.2 |
|---|---|
| CI sub-checks | 122 → **133** (+11) |
| Pest scenarios | 505 → **542** (+37) |
| Unique Pest helpers | 123 → **129** (+6 p11b12_*) |
| New migrations | — → **1** |
| New seeders | — → **1** |
| New models | — → **1** (ProductTranslation) |
| New services | — → **1** (TranslationService) |
| New interfaces / providers | — → **2** |
| New queue jobs | — → **1** |
| New Filament resources | — → **1** + 2 pages |
| Product model translation accessors | 3 (v11B.1.1) → **3** (now delegate to TranslationService) |
| SearchBar instances safely concurrent | 2 → **3** with unique namespaced IDs |

---

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/

php artisan migrate:status | grep 2026_06_28
php artisan migrate

# Migrate existing JSON content into product_translations (status=approved)
php artisan db:seed --class=BackfillProductTranslationsSeeder

# Verify
php artisan translations:audit ar
# Should show new "Product translation workflow status" section with status counts

php artisan route:list | grep -i search
npm ci && npm run typecheck && npm run build

php artisan test --filter=Phase11B12   # 37 v11B.1.2 scenarios
php artisan test                        # 542 total
```

---

## Rollback (3-tier, per v11B.1 precedent)

### Tier 1 — instant feature-flag rollback
None for v11B.1.2 per se; but the resolver's behavior can be tuned via:
- `MARKETPLACE_PUBLIC_REVIEWED=true` to also show human_reviewed translations
- All v11B.1 feature flags (SEARCH_FEATURE_*) remain available

### Tier 2 — partial revert
```bash
# Replace ONLY the resolver + observer (keep table for data preservation):
tar -xzf marketplace-phase-11B-1-1-arabic-mobile-search.tar.gz \
    --strip-components=1 --overwrite \
    marketplace/app/Services/Localization/TranslationService.php \
    marketplace/app/Models/Product.php \
    marketplace/app/Providers/AppServiceProvider.php \
    marketplace/resources/js/Components/common/SearchBar.tsx \
    marketplace/resources/js/Pages/Catalog/Index.tsx
php artisan optimize:clear && npm run build
```

### Tier 3 — full revert to v11B.1.1
```bash
# 1. Drop the v11B.1.2 table
php artisan migrate:rollback --step=1

# 2. Extract v11B.1.1 archive
tar -xzf marketplace-phase-11B-1-1-arabic-mobile-search.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear && npm ci && npm run build
cat VERSION  # → Phase 11B.1 v11B.1.1
```

The legacy JSON columns (`name_translations`, `short_description_translations`, `description_translations`) remain populated, so reverting v11B.1.2 keeps all Arabic content visible — the v11B.1.1 resolver reads them directly.

---

## Phase 11B.1 v11B.1.2 STOPS HERE

No Phase 11B.2 work begun. Pending dev verification per §27 manual translation test + §28 mobile search test at 320 / 375 / 390 / 414px + §31 commands + §32 evidence capture.
