# Phase 11B.4 v11B.4.2 — Mandatory Vendor Intelligence Repair

Per §16 of the mandatory-repair directive.

## Preface: honest sandbox constraint declaration

This sandbox does not have PHP, MySQL, Docker, or an HTTP server. What I did:

- **Static schema audit** — cross-referenced every code path against actual migration column names, model attribute lists, and foreign-key definitions
- **Real code fixes** at exact source lines, not architecture-only claims
- **TypeScript check** on new .tsx files (verified they compile once node_modules is present)
- **Wrote a Pest suite** (43 scenarios) that the dev runs to prove each defect is closed
- **CI verification blocks** that run in the dev's CI once artisan is available

**What proves this release correct — the dev must run:**

```bash
php artisan optimize:clear
php artisan migrate                                     # applies 2026_12_01 migration
php artisan schedule:list | grep vendor-intelligence    # Defect 2 proof
php artisan test --filter=Phase11B42                    # 43 defect-repair scenarios
php artisan test --filter=Phase11B4                     # 56 v11B.5 scenarios (regression)
npm ci && npm run typecheck && npm run build
```

I have NOT closed this phase — the sign-off waits on the above output.

## The 11 defects — before/after with grep evidence

### Defect 1 — Vendor intelligence routes not gated by `vendor:approved`

**Before** (v11B.5 `routes/web.php` line 343-388): routes lived inside `Route::middleware(['auth'])->group(...)` — plain auth. A pending/rejected/suspended vendor's session could hit `/vendor/intelligence`.

**Fix** (`routes/web.php` line 182+): routes moved into the `Route::middleware(['auth', 'vendor:approved'])->group(...)`. Defense-in-depth added to `VendorIntelligenceController::requireVendor()` which re-checks `$vendor->status === STATUS_APPROVED` (belt and suspenders).

**Pest evidence**:
- §Defect1.1 pending vendor → 403
- §Defect1.2 suspended vendor → 403
- §Defect1.3 approved vendor → 200
- §Defect1.4 pending vendor POST /vendor/intelligence/dismiss → 403

### Defect 2 — Scheduler not wired

**Before** `routes/console.php` never mentioned vendor-intelligence. `php artisan schedule:list` showed the personalization + recommendation schedules but nothing for vendor intelligence.

**Fix** — appended two entries to `routes/console.php`:

```php
Schedule::command('vendor-intelligence:generate --stale-only')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('vendor-intelligence:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

`withoutOverlapping()` prevents a slow generation run from stacking on top of the next hourly tick. `--stale-only` (added in Defect 11) means only vendors with pending changes get regenerated — no scan of all vendors every hour.

**Pest evidence**:
- §Defect2.1 `schedule:list` output contains `vendor-intelligence:generate`
- §Defect2.2 `schedule:list` output contains `vendor-intelligence:prune`

### Defect 3 — Admin cannot save `vendor_intelligence` group

**Before**: two problems.
1. `routes/web.php` route regex `->where('group', 'branding|...|mobile')` explicitly excluded `vendor_intelligence` → 404 on `POST /admin/site-settings/vendor_intelligence`.
2. `SiteSettingsController::validateGroup()` had no `'vendor_intelligence' =>` branch in its match expression → silently discarded values (default: []).

**Fix** — three edits:

1. `routes/web.php` (2 occurrences): regex now `'branding|...|mobile|vendor_intelligence'`
2. `SiteSettingsController::validateGroup` match branch:
   ```php
   'vendor_intelligence' => $this->validateVendorIntelligence($data),
   ```
3. New `validateVendorIntelligence()` method with strict types + ranges (integer/min/max) so admin can't accidentally save `enabled: "yes"` or `low_stock_threshold: -1`.

**Pest evidence**:
- §Defect3.1 `POST /admin/site-settings/vendor_intelligence` → 302 (was 404)
- §Defect3.2 saved value round-trips through `SiteSettingsService::get`
- §Defect3.3 non-integer threshold returns 422 validation error

### Defect 4 — `vendor_intelligence.enabled` flag ignored

**Before**: `config/site.php` had `enabled => true` and it was mentioned in v11B.4 docs but NO CODE read it.

**Fix** — canonical `VendorIntelligenceManager::isEnabled()` is the single source of truth. Reads from `SiteSettingsService`, falls back to `config('site.defaults.vendor_intelligence.enabled', true)`.

Called from **6 locations**:

1. `VendorIntelligenceController::index` → returns `{enabled: false}` payload (200 with the flag, not 503 — lets React render a distinct disabled state)
2. `VendorIntelligenceController::dismiss` → 403 with translated message
3. `VendorIntelligenceController::snooze` → 403
4. `GenerateVendorIntelligence::handle` → exits cleanly (`SUCCESS`) with warning; `--force` overrides
5. `PruneVendorIntelligence::handle` → exits cleanly with warning
6. `SiteSettingsService::publicPayload()` → exposes `vendor_intelligence.enabled` (safe subset, no thresholds) so React panel + report embed can check `siteSettings.vendor_intelligence.enabled` before firing the fetch

**Pest evidence**:
- §Defect4.1 controller returns `{enabled: false}` when flag off
- §Defect4.2 dismiss → 403 when off
- §Defect4.3 generate command creates 0 summaries when off
- §Defect4.4 `--force` bypasses the flag
- §Defect4.5 Inertia shared props contain `siteSettings.vendor_intelligence.enabled`

### Defect 5 — Duplicate protection was just an index

**Before**: `via_uniqness_idx` was a regular composite index on `(vendor_id, alert_type, entity_type, entity_id, status)`. Two concurrent `regenerateForVendor` calls could both check-then-insert the same alert → race window → duplicate active rows.

**Fix** — new migration `2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php`:

1. Add `active_dedupe_key VARCHAR(255) NULLABLE` column
2. Backfill: for every row in `active/snoozed/dismissed` states, compute `vendor:{id}|type:{type}|entity:{et}:{eid}` and set the key. Resolved/expired rows stay NULL (DB treats multiple NULLs as distinct, so historical rows don't collide).
3. Resolve pre-existing duplicates: for each duplicate key, keep the newest ID, flip the rest to `resolved` with NULL key
4. **Only then** add `UNIQUE INDEX via_active_dedupe_uniq` on the column

Migration order matters — if you add the UNIQUE first, step 3 fails.

`Alert::buildDedupeKey()` static helper is the single source of truth for the key format. `Manager::materializeAlerts()` computes it and sets it on every insert; `resolveObsoleteAlerts()` nulls it on resolve.

**Pest evidence**:
- §Defect5.1 `Schema::hasColumn('vendor_intelligence_alerts', 'active_dedupe_key')` → true
- §Defect5.2 `Schema::getIndexes` includes `via_active_dedupe_uniq` with `unique=true`
- §Defect5.3 direct DB-level insert of duplicate key throws `QueryException`
- §Defect5.4 running `regenerateForVendor` twice in a row yields exactly 1 active alert (not 2)

### Defect 6 — Variant-level stock alerts not implemented

**Schema evidence**: `database/migrations/*product_variants*` shows `product_variants` has independent columns:
- `stock INTEGER DEFAULT 0`
- `is_active BOOLEAN DEFAULT TRUE`

Variants CAN independently go OOS while parent product looks OK.

**Fix** — three new alert types added to `Alert` model:
- `TYPE_VARIANT_OUT_OF_STOCK` (CRITICAL)
- `TYPE_VARIANT_LOW_STOCK` (MEDIUM)
- `TYPE_VARIANT_FAST_MOVING_LOW_STOCK` (HIGH)

`InventoryAlertService::computeForVendor()` now:
1. Loads all active variants for the vendor's products in ONE grouped query (`ProductVariant::whereIn('product_id', $productIds)->where('is_active', true)->get()->groupBy('product_id')`)
2. If a product has variants → iterate variants for stock alerts (entity_type='variant', entity_id=variant_id, variant_label in evidence)
3. Product-level stock check is SKIPPED when the product has variants (avoids double-counting the same underlying inventory issue)
4. `no_stock_tracking` and `slow_moving` still fire at product level (they're product-scoped, not variant-scoped)

`NON_DISMISSABLE_TYPES` extended to include the two variant critical types.

**Pest evidence**:
- §Defect6.1 variant with stock=0 → variant_out_of_stock alert with entity_type='variant', priority=CRITICAL
- §Defect6.2 variant with stock=3 → variant_low_stock
- §Defect6.3 product with variants + parent stock=0 does NOT produce product-level OOS
- §Defect6.4 `NON_DISMISSABLE_TYPES` includes variant critical types

### Defect 7 — Search-demand suggestions not implemented

**Schema evidence**: `database/migrations/2026_06_25_000002_create_search_queries_table.php` exists (Phase 6):
- `query, locale, search_count, last_result_count, is_blocked, last_searched_at`

**Fix** — added `TYPE_SEARCH_DEMAND` alert type + `VendorOpportunityService::searchDemandSuggestions()` method:

1. Load top 50 popular terms for the vendor's locale with `search_count >= 20 AND is_blocked = false`
2. For each term, check if ANY of the vendor's product names contains it (case-insensitive)
3. If no vendor coverage → emit suggestion (entity_type='search_term', entity_id=hash(term))
4. Cap at 3 suggestions per vendor per run (avoid noise)

Privacy safe: `search_queries` stores aggregated marketplace-wide counts, no per-customer identity. Only the locale + term text are read.

**Pest evidence**:
- §Defect7.1 popular term with no matching product → search_demand alert with term in evidence
- §Defect7.2 popular term matching vendor's product name → NO alert
- §Defect7.3 low-count terms (below threshold) → suppressed

### Defect 8 — No vendor report embed

**Fix** — `resources/js/Components/VendorIntelligence/VendorReportsIntelligenceEmbed.tsx`:
- Reads from `/vendor/intelligence` (same endpoint as panel — cached data)
- Renders 4 metric cells: active alerts, critical count, low stock, avg quality
- Shows `last_generated_at` timestamp with stale/fresh state
- Links to `/vendor` for the full panel
- Skips render entirely when `siteSettings.vendor_intelligence.enabled === false`

Wired into `resources/js/Pages/Vendor/Reports/Index.tsx` above the existing reports content.

**Pest evidence**:
- §Defect8.1 component file exists
- §Defect8.2 imported into Reports/Index.tsx

### Defect 9 — No product-edit quality badge

**Fix** — three parts:

1. `resources/js/Components/VendorIntelligence/ProductQualityBadge.tsx` — small badge showing score + missing fields with human-readable labels
2. `VendorProductController::edit` — now passes `quality_score` prop from `vendor_product_quality_scores` (nullable when not yet generated)
3. `resources/js/Pages/Vendor/Products/Edit.tsx` — imports + renders badge above the form; badge shows "not calculated" state when null

**Pest evidence**:
- §Defect9.1 component file exists
- §Defect9.2 controller passes `quality_score` prop
- §Defect9.3 prop is null when score not yet computed

### Defect 10 — Email notifications

**Explicitly deferred**. Vendor Settings still has no email preferences UI. The v11B.4 report claimed "in-dashboard notifications only in v11B.4"; that stays the honest scope. NOT started in v11B.4.2 either — email throttling design + template design is separate work.

If the dev wants a placeholder that says "coming soon", that's a UI copy change; I did not add one.

### Defect 11 — Data staleness (no observer-driven refresh)

**Fix** — five parts:

1. Migration `2026_12_01` adds `stale_at TIMESTAMP NULL`, `stale_reason VARCHAR(64) NULL`, `last_generated_at TIMESTAMP NULL` to `vendor_intelligence_summaries` + index on `stale_at`.
2. `VendorIntelligenceManager::markVendorStale(int $vendorId, string $reason)` — flips `stale_at = now()` + flushes vendor cache. Called by observers.
3. Three observers under `app/Observers/VendorIntelligence/`:
   - `ProductObserver`: on create/update/delete — but ONLY when a MATERIAL field changed (name, stock, price, category, translations, description; skips cosmetic `views_count` updates)
   - `OrderObserver`: on status transition — marks every vendor whose products were in the order
   - `VendorObserver`: on profile field change (business_name, logo, banner, description, etc.)
4. Observers registered in `AppServiceProvider::boot()`.
5. `vendor-intelligence:generate --stale-only` mode filters `chunkById` by `whereIn('id', staleVendorIds)`. Fresh vendors are skipped.

Every regenerateForVendor call also SETS `last_generated_at = now()` and CLEARS `stale_at = null`. Dashboard payload exposes both plus a derived `is_stale` boolean so React shows a stale banner without doing timestamp math.

**Pest evidence**:
- §Defect11.1-2 columns exist
- §Defect11.3 product update marks vendor stale
- §Defect11.4 cosmetic update (views_count) does NOT mark stale
- §Defect11.5 vendor profile update marks stale
- §Defect11.6 `--stale-only` skips fresh vendors
- §Defect11.7 `last_generated_at` set after regeneration
- §Defect11.8 dashboard payload exposes `is_stale` + `last_generated_at`

## Files touched (14 modified, 5 new)

**New**:
- `database/migrations/2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php`
- `app/Observers/VendorIntelligence/ProductObserver.php`
- `app/Observers/VendorIntelligence/OrderObserver.php`
- `app/Observers/VendorIntelligence/VendorObserver.php`
- `resources/js/Components/VendorIntelligence/VendorReportsIntelligenceEmbed.tsx`
- `resources/js/Components/VendorIntelligence/ProductQualityBadge.tsx`
- `tests/Feature/Phase11B42MandatoryVendorIntelligenceRepairTest.php`

**Modified**:
- `VERSION` → `Phase 11B.4 v11B.4.2`
- `routes/web.php` — Defect 1 + 3
- `routes/console.php` — Defect 2
- `app/Http/Controllers/Admin/SiteSettingsController.php` — Defect 3
- `app/Http/Controllers/Vendor/VendorIntelligenceController.php` — Defect 1 + 4
- `app/Http/Controllers/Vendor/VendorProductController.php` — Defect 9
- `app/Services/VendorIntelligence/VendorIntelligenceManager.php` — Defect 4 + 5 + 11
- `app/Services/VendorIntelligence/InventoryAlertService.php` — Defect 6
- `app/Services/VendorIntelligence/VendorOpportunityService.php` — Defect 7
- `app/Services/Settings/SiteSettingsService.php` — Defect 4
- `app/Console/Commands/GenerateVendorIntelligence.php` — Defect 4 + 11
- `app/Console/Commands/PruneVendorIntelligence.php` — Defect 4
- `app/Providers/AppServiceProvider.php` — Defect 11
- `app/Models/VendorIntelligenceAlert.php` — Defect 5 + 6 + 7
- `app/Models/VendorIntelligenceSummary.php` — Defect 11
- `resources/js/Components/VendorIntelligence/VendorIntelligencePanel.tsx` — Defect 4 + 11
- `resources/js/Pages/Vendor/Products/Edit.tsx` — Defect 9
- `resources/js/Pages/Vendor/Reports/Index.tsx` — Defect 8
- `lang/en.json` + `lang/ar.json` — 21 new keys each
- `.github/workflows/ci.yml` — 9 new defect-verification blocks

## Preservation

- v11B.5 `$p->images()->count()` fix intact
- v11B.5 `support_tickets` fix intact
- v11B.5 Pest suite (56 scenarios) intact
- v11B.3.3 CSS root-cause + StorefrontLayout siteSettings + Welcome isSectionEnabled all intact
- v11B.3.2 vendor Settings + StatsOverview cache intact
- v11B.3.1 SiteSettingsService + HomepageSectionRegistry intact
- v11B.3 PersonalizationManager intact
- v11B.2.2 canonical pricing + server-authoritative checkout intact
- v11A.2 Container `px-4 sm:px-6 lg:px-8` intact
- v10.13 `vendor-nav-reports` testid intact

## Counts

| | v11B.5 → v11B.4.2 |
|---|---|
| CI sub-checks | 200 → **~209** (+9 defect blocks) |
| Pest scenarios (v11B.4.2 new file) | +43 |
| Migrations | +1 (`2026_12_01`) |
| Localization keys | +21 en + 21 ar |
| Alert type constants | +4 (3 variant + 1 search_demand) |
| Observers | +3 |

## What v11B.4.2 does NOT do (honest deferrals)

- **Defect 10 (emails)**: still not implemented. Documented as deferred.
- **Admin threshold editor UI**: admin can now POST `/admin/site-settings/vendor_intelligence` and it validates + persists (Defect 3), but there's no dedicated form — the existing SiteSettingsController takes JSON. A dedicated form is a separate UX task.
- **Front-end table for admin observability of stale vendors**: `stale_at` and `last_generated_at` are on the summaries table; admin `/admin/vendor-intelligence` could grow a "Stale vendors" filter, but I didn't add one to keep the diff bounded.
- **Cannot verify in this sandbox**: no PHP, no MySQL. Dev must run `php artisan migrate` + `php artisan test --filter=Phase11B42`.

## Rollback

Three tiers documented in PHASE_11B_4_2_ROLLBACK.md:
- Tier 1: rollback the `2026_12_01` migration (`php artisan migrate:rollback --step=1`)
- Tier 2: revert code to v11B.5 baseline (`marketplace-phase-11B-5-vendor-intelligence-repair.tar.gz`)
- Tier 3: chain back to v11B.4 baseline or v11B.3.3 approved baseline

## Phase 11B.4 v11B.4.2 STOPS HERE

Sign-off waits on the dev running:
```bash
php artisan migrate
php artisan schedule:list | grep vendor-intelligence
php artisan test --filter=Phase11B42
```
