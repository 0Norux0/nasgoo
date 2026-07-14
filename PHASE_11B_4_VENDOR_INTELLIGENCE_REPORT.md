# Phase 11B.4 — Vendor Intelligence: Smart Suggestions, Inventory Alerts & Performance Guidance

Per dev §46.

## Baseline vendor dashboard behavior (pre-v11B.4)

Baseline `/vendor` renders `Vendor/Dashboard.tsx` (a status banner + business header + package/commission cards + profile completion). No stock alerts, no product quality signals, no action checklist. Vendors received no guidance about which products need attention.

## Available data signals (§4 audit)

Confirmed present in the schema at v11B.3.3:

| Signal | Table | Used by |
|---|---|---|
| Product data + track_stock + stock | `products` | InventoryAlertService, ProductQualityService |
| Product translations (name_translations, description_translations) | `products` JSON columns | ProductQualityService (i18n scoring) |
| Product images (JSON array) | `products.images` | ProductQualityService (media scoring) |
| Product views | `customer_product_views` (v11B.3) | VendorOpportunityService |
| Wishlist adds | `wishlists` | VendorOpportunityService |
| Cart adds | `cart_items` | VendorOpportunityService |
| Completed orders + revenue | `orders` + `order_items` (v11B.2.2 snapshot) | VendorPerformanceService |
| Vendor profile completeness | `vendors` (business_name, logo_path, banner_path, description, address, etc.) | VendorActionChecklistService |
| Support tickets | `tickets` | VendorActionChecklistService |

## Unavailable / deliberately excluded signals (§3)

Not read by v11B.4 by design:
- Customer names, emails, identity documents, precise location
- Payment card information
- Hidden admin notes (`vendors.admin_notes`)
- Private support-ticket message bodies
- Other vendors' confidential per-product data (§24 — vendor A cannot see vendor B's alerts, quality scores, or performance)

## Final architecture (§5)

```
app/Services/VendorIntelligence/
    VendorIntelligenceManager.php        (orchestrator)
    InventoryAlertService.php            (§7 stock-based alerts)
    ProductQualityService.php            (§9 §30 weighted quality score)
    VendorOpportunityService.php         (§11 sales opportunity rules)
    VendorPerformanceService.php         (§13 best-selling highlights)
    VendorActionChecklistService.php     (§14 §16 store completion + checklist)
    VendorIntelligenceCacheService.php   (§27 vendor-isolated cache)
```

Manager coordinates; individual services stay small and testable. Controllers contain no scoring logic — they only marshal input/output.

## Data model (§26)

4 additive tables (`2026_11_01_000001_create_vendor_intelligence_tables.php`):

```
vendor_intelligence_summaries       (one row per vendor — rollup)
vendor_intelligence_alerts          (individual actionable alerts)
vendor_intelligence_feedback        (vendor's dismiss/snooze decisions)
vendor_product_quality_scores       (one row per product)
```

Indexes:
- `vis_vendor_uniq` on `summaries.vendor_id` (UNIQUE — one row per vendor)
- `via_vsp_idx` on `alerts(vendor_id, status, priority)` — the exact filter used by the dashboard
- `via_uniqness_idx` on `alerts(vendor_id, alert_type, entity_type, entity_id, status)` — supports the idempotent-alert lookup
- `via_status_expiry_idx` on `alerts(status, expires_at)` — for the prune command
- `vif_lookup_idx` on `feedback(vendor_id, suggestion_type, entity_type, entity_id)` — dismiss-check
- `vpqs_product_uniq` on `quality_scores.product_id` (UNIQUE)
- `vpqs_vendor_score_idx` on `quality_scores(vendor_id, score)` — admin sort

All foreign keys use `cascadeOnDelete`. Rollback drops all four tables.

## Inventory alert rules (§7)

Each rule reads real fields. `unlimited stock` (digital / service / `track_stock=0`) is never treated as low stock (§7 caveat).

| Alert type | Rule | Priority |
|---|---|---|
| `out_of_stock` | `track_stock=1 AND stock=0` | **critical** |
| `low_stock` | `track_stock=1 AND 0 < stock ≤ threshold` | medium |
| `fast_moving_low_stock` | low_stock AND completed-orders-last-N-days ≥ min | **high** |
| `slow_moving` | published, in stock, age ≥ min_age, no completed order for ≥ slow_days | medium |
| `no_stock_tracking` | physical product with `track_stock=0` | low |

Thresholds are all admin-tunable via `siteSettings.vendor_intelligence.*` (§20).

## Product quality score formula (§9 §30)

Score 0-100. Weighted composition (defaults, admin-configurable):

| Group | Default weight | Checks |
|---|---:|---|
| core | 30 | title, category assigned, price > 0, published status |
| media | 20 | has at least 1 image, has 3+ images |
| i18n | 20 | Arabic title present, Arabic description present |
| inventory | 15 | stock configured for physical, has stock or unlimited |
| seo | 10 | slug present, short_description present |
| policy | 5 | full description present + ≥ 50 chars stripped |

Each group's per-check pass-rate is multiplied by the group weight and summed. Digital + service products don't require stock config (§30 "Do not penalize products for fields the system does not support").

Weights are read from `siteSettings.vendor_intelligence.quality_weights` at compute time. If the weights don't sum to 100, defaults are used (safe fallback).

## Opportunity rules (§11)

Minimum-evidence gating (dev §11 "Do not show suggestions based on tiny data volume"):

| Alert type | Formula | Default threshold |
|---|---|---|
| `high_view_low_conversion` | views ≥ min AND (purchases/views) < ceil | min=100, ceil=1% |
| `wishlist_interest` | wishlists ≥ min AND purchases < wishlist/4 | min=10 |
| `cart_abandonment` | (cart_adds − purchases) ≥ min | min=10 |
| `promotion_opportunity` | HVLC + zero purchases | (subsumes HVLC) |

Never derives revenue from current price — uses `order_items.line_total_minor` snapshot (v11B.2.2 §29).

## Slow-moving product logic (§12)

- Product must be `published` (not draft, not archived, not rejected)
- Must have stock (or be unlimited)
- Must have existed for ≥ `slow_moving_min_age_days` (default 30) so brand-new listings aren't punished (dev §12)
- Must have zero completed orders in the last `slow_moving_days` (default 60)

Suggestions surface: improve content, add images, add Arabic, consider promotion — never "discount to X%" (dev §12: pricing recommendations are a later phase).

## Best-seller logic (§13)

Vendor's own products, joined with `order_items` (snapshot pricing) + `orders` filtered to the completed-order set (`PAID / CONFIRMED / SHIPPED / DELIVERED / COMPLETED`). Excludes `PENDING_PAYMENT`, `CANCELLED`, `REFUNDED`, `FAILED` (§29). Grouped by product, top 5 by units_sold in the last N days.

Also computes most-viewed (last N days) + most-wishlisted (all-time). Every result set is JOINed with `products.vendor_id = current_vendor.id` — never accidentally leaks another vendor's rows.

## Store completion logic (§14)

8 baseline fields checked (business_name, logo_path, banner_path, description, business_email, business_phone, address, country). Plus Arabic description if the vendor stores multi-locale content. Score = passed / total × 100. Missing field list surfaces in the dashboard checklist.

## Alert lifecycle (§32)

```
                    ┌────────────┐
    (regeneration)  │            │
       ─────────►   │  ACTIVE    ├──────► resolved (condition no longer exists)
                    │            │
                    └─┬────────┬─┘
                      │        │
             vendor   │        │  vendor
             dismiss  ▼        ▼  snoozes
                    ┌───────────┐  ┌───────────┐
                    │ DISMISSED │  │ SNOOZED   │
                    │           │  │           │
                    └───────────┘  └────┬──────┘
                                        │
                                (prune finds expires_at ≤ now)
                                        │
                                        ▼
                                 ACTIVE again
```

- `active` → dashboard shows it, ranked by priority
- `dismissed` — hidden. Reappears on next regen only if it's a critical type (out_of_stock / fast_moving_low_stock).
- `snoozed` with `expires_at` set. Hidden until expires_at ≤ now, then automatically flipped back to `active` by the `prune` command.
- `resolved` — condition no longer exists (regeneration didn't produce this alert). Kept for 90 days for audit, then deleted by `prune`.
- `expired` — not currently used; reserved for future feature-flag-driven expiration.

Idempotency: on regeneration, `materializeAlerts()` checks for an existing active/snoozed row with the same (vendor, type, entity_type, entity_id). If found, only evidence + priority are updated — no duplicate row is created (dev §32.11).

## Dismissal / snooze behavior (§17)

- `dismiss` writes a row to `vendor_intelligence_feedback` and flips the current alert to `dismissed`. On next regeneration, the manager checks the feedback table BEFORE creating a new active alert; if a dismissal exists AND the alert type is not in `NON_DISMISSABLE_TYPES`, the alert is suppressed.
- `snooze` writes feedback + sets alert status to `snoozed` with `expires_at = now + N days` (N configurable, default 7).
- Critical types (`out_of_stock`, `fast_moving_low_stock`) can't be permanently dismissed — the manager `return`s early. Pest §36.29 verifies this.
- Vendor A can never dismiss vendor B's alerts. Identity comes from `$request->user()->vendor` in the controller (Pest §37.36 — hostile `entity_id` in request body only affects vA's own row).

## Notification preferences (§18)

Dashboard notifications are the default (always on). Email preferences deferred (see Limitations) — v11B.4 does not send emails.

## Admin visibility (§19)

`/admin/vendor-intelligence` (super_admin only, enforced by controller `abort_unless`). Shows:
- Rollup: total_vendors, total_alerts, avg_completion, avg_quality
- Per-vendor table with filter chips (`low_stock`, `incomplete_stores`, `missing_arabic`, `many_pending`)
- Paginated (25 per page)
- Uses shared `ResponsiveDataList` (v11B.3.1 preserved) — desktop table + mobile card, no letter-by-letter wrapping (v11B.3.3 CSS fix preserved)

Admin sees business_name + business_email only — no customer identity, no private ticket text.

## Configuration settings (§20)

All threshold values live under `siteSettings.vendor_intelligence.*` via the existing `SiteSettingsService` (v11B.3.1 architecture reused per dev §20 "Do not create another settings system"):

```
low_stock_threshold          5     units
fast_moving_days             30    days
fast_moving_min_orders       5     orders in window
slow_moving_days             60    days
slow_moving_min_age_days     30    days
min_views_for_conversion     100   views
high_view_conversion_ceil    0.01  (1%)
min_wishlist_interest        10    wishlists
min_cart_abandonment         10    cart − purchases
dashboard_alert_limit        10
default_snooze_days          7
quality_weights              {core:30, media:20, i18n:20, inventory:15, seo:10, policy:5}
```

## Cache strategy (§27 §37)

`VendorIntelligenceCacheService::dashboardKey(vendorId, locale)` returns `"vi:v11b4:dash:{vendorId}:{locale}:v1"`. Vendor ID is **required in the key** — one vendor's dashboard is never served from another vendor's cache (Pest §37.35). TTL 15 minutes. Defensive `try/catch` — cache driver failure falls back to fresh computation.

## Invalidation strategy (§27)

`regenerateForVendor()` calls `cache.flush($vendorId)` at the end of a transactional refresh. `dismissSuggestion()` and `snoozeSuggestion()` also flush. Threshold changes in admin settings clear the SiteSettingsService cache (already handled by v11B.3.1 `flushGroup`). No global cache flush unless admin thresholds change materially.

## Localization (§21)

34 new keys in each of en/ar:
- `vendor_intelligence.loading`, `.alerts_title`, `.summary.*`, `.alerts.{type}.title`
- `checklist.*` (5 items)
- `admin.vendor_intelligence.title`, `.subtitle`
- `common.review`

All UI strings pipe through `t()` — no hardcoded Arabic placeholders (dev §21 "no hardcoded Arabic placeholders").

## Mobile verification (§23 §42)

The panel uses only shared primitives — no new CSS rules. Preserves v11B.3.3 root-cause fix (no letter-by-letter wrapping). Summary cards use `grid-cols-2 md:grid-cols-4`. Alert rows use `flex items-start gap-3` — priority badge (whitespace-nowrap), text (min-w-0 flex-1 for truncate), action buttons (flex-shrink-0). All action buttons have `whitespace-nowrap`.

Manual tests at 320/375/414 required per §42; Pest §23 verifies component structure.

## Privacy / vendor isolation (§24)

Server-side enforcement, not just UI:
- Every read query joins `products.vendor_id = $vendor->id` OR filters `vendor_id = $vendor->id`
- Controllers derive vendor from `$request->user()->vendor` — never from request body
- Cache keys include `vendorId` — no accidental cross-vendor cache serving
- Admin overview only shows aggregate columns from `vendor_intelligence_summaries` joined with `vendors.business_name`, never customer-level data
- `NON_DISMISSABLE_TYPES` protection prevents an attacker from suppressing critical alerts even with a valid session

## Performance evidence (§43)

| Page/Command | Query count | Notes |
|---|---:|---|
| Vendor dashboard (cache hit) | 0 (served from cache) | 15-min TTL |
| Vendor dashboard (cache miss, 10 products) | ≤25 queries (Pest §38.39) | grouped counts + eager loads |
| `vendor-intelligence:generate --vendor=1` | ~5-8 per product | chunked, transactional |
| `vendor-intelligence:generate` (batch) | chunkById(100) — memory bounded | logged per batch |
| `vendor-intelligence:prune` | 2 queries | UPDATE + DELETE |
| Admin overview | 1 paginated select + 1 rollup | joined with vendors |

## Automated tests (§34-§38)

`tests/Feature/Phase11B4VendorIntelligenceTest.php` — **53 Pest scenarios**:

| Group | Scenarios | Coverage |
|---|---|---|
| §34 Inventory | 12 | OOS, low, unlimited-excluded, no_stock_tracking, fast_moving_HIGH, slow_moving, recent-product-excluded, suspended-vendor-excluded, draft-excluded, resolve, dedup, snooze-hides |
| §35 Quality | 11 | Complete=high, missing images/AR/category/stock/SEO lower score, digital not penalized, missing list accurate, persistence, vendor isolation |
| §36 Opportunities | 6 | HVLC, wishlist, cart abandonment, evidence-threshold, dismissed-hidden, critical-cannot-dismiss |
| §37 Permissions + dashboard | 9 | Vendor 200, customer 403, guest redirect, admin overview, vendor cannot access admin, cache-key isolated, hostile-body-ignored, feature-flag, threshold-defaults |
| §38 Performance + regression | 6 | ≤25 queries, idempotent, batch, prune-deletes-old, prune-un-snoozes, cache-invalidation |
| Regression across all prior phases | 9 | v11B.3.3 CSS + StorefrontLayout; v11B.3.2 vendor Settings + StatsOverview; v11B.3.1 SiteSettingsService; v11B.3; v11B.2.2 pricing; v10.13 testid |

## Manual verification (§40-§42)

Deferred to developer environment. Test dataset creation is documented in §39.

## Unresolved limitations

Honest scope:

- **Variants**: variant-specific stock alerts not implemented yet — parent product stock only. Products with variants get parent-level alerts. §28 "alert per variant where stock differs" is deferred pending variant-model exploration.
- **Email notifications** (§18): only in-dashboard notifications in v11B.4. Email throttling + template design deferred.
- **Product-detail-page intelligence badge** (§22): dashboard-only surface for v11B.4. Product edit page can consume `vendor_product_quality_scores` in a future release.
- **Vendor reports embed** (§31): the intelligence panel is only on the dashboard. Adding a light insights section to `/vendor/reports` deferred.
- **Search-demand suggestion** (§11): requires a search-analytics table which doesn't exist yet. Skipped — no fake data.
- **Product-category benchmarking**: no "vendors in your category convert at X%" comparison. Would require cross-vendor aggregation with privacy safeguards.
- **Admin threshold editor UI**: thresholds are configurable via `SiteSettingsService::set()` but there's no dedicated admin form for them yet — admin edits raw JSON in the existing `/admin/site-settings` page.
- **Scheduled generation**: `vendor-intelligence:generate` is a manual command. A Laravel scheduler entry for nightly regeneration is documented but not wired (developer preference).

## Package integrity confirmation

Workspace verification: **70/71 checks pass** (1 = false positive on whitespace formatting). 169 unique Pest helpers, 0 duplicates. All prior phases intact. CI YAML valid, 7 v11B.4 sub-checks.

See `PHASE_11B_4_PACKAGE_INTEGRITY.md` for per-file SHA-match table.

## Phase 11B.4 STOPS HERE

Not started per directive: smart pricing, advanced demand forecasting, plain-language report narratives, support assistant, risk/fraud scoring, customer credit scoring. Pending developer verification.
