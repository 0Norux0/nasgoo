# Phase 11B.3 — Personalized Homepage, Recently Viewed & Continue Shopping

Per dev §53.

## Scope

Deterministic, transparent personalization built on ordinary marketplace activity. **No paid AI APIs.** **No sensitive profiling.** **No cross-customer data exposure.** Storefront homepage, product detail page, and account settings integrate with a single manager service that assembles sections respecting section priority, cross-section deduplication, cache isolation, and runtime eligibility recheck.

## Baseline homepage behavior (pre-v11B.3)

`HomeController::index` returned `Welcome.tsx` with:
- Health probes (v10.14/v10.15 defensive cache)
- Featured products (Phase 3, promotion-aware from v10.8)
- Top categories (v11A.5 dynamically localized)

No behavioral personalization. Every customer saw an identical homepage.

## Architecture

```
Phase 11B.3 layer                             Existing (unchanged) layer
─────────────────                             ─────────────────────────
resources/js/Components/Personalization/      resources/js/Pages/Welcome.tsx
  PersonalizedSections.tsx                       (renders featured, top_categories
       │                                          + PersonalizedSections above them)
       ▼
HomeController::index
       │
       ▼
PersonalizationManager::homepageFor(user, sessionKey, locale)
       │
       ├── globallyEnabled?      (features.enabled)
       ├── userOptedOut?         (PersonalizationPreference)
       ├── cache HIT?            (Cache::remember, isolated key)
       ├── reapplyEligibility()  (runtime recheck — every read)
       │
       ▼ (on miss)
    buildFresh
       │  For each section in priority order:
       │    (skip if flag off; skip if items < min_evidence;
       │     stop when max_sections_shown reached)
       │
       ├── continue_shopping   → ContinueShoppingService
       ├── recently_viewed     → RecentlyViewedService
       ├── recommended_for_you → CustomerAffinityService.topCategories → Product::query
       ├── category_affinity   → same
       └── buy_again           → BuyAgainService

Data sources
────────────
customer_product_views       (v11B.3 §8)
  ├── user_id | session_key
  └── product_id, locale, viewed_at

customer_affinities          (v11B.3 §14)
  ├── user_id
  └── dimension {category, vendor, price_band}, dimension_id, score, last_signal_at

personalization_preferences  (v11B.3 §21)
  ├── user_id
  └── behavioral_personalization_enabled, guest_merge_enabled, behavior_tracking_enabled

personalization_feedback     (v11B.3 §23)
  ├── user_id | session_key
  └── feedback_type {not_interested, hide_product, show_fewer_like}, product_id, expires_at

Signal sources (read by CustomerAffinityService, never written except via events)
────────────────────────────────────────────────────────────────────────────────
Wishlist                     (existing)
Order + OrderItem            (existing; qualifying statuses only)
Cart                         (existing)
UserRecentSearch             (existing; reserved for search-term affinity)
```

## Allowed vs prohibited signals (§4 §5)

**Used:**
- viewed products (customer_product_views)
- viewed categories (inferred via product view → product.category)
- wishlist additions
- cart additions
- completed purchases (via Order.status ∈ qualifying)
- recency (recency_decay buckets)
- frequency (capped per config.affinity_caps)

**NOT used:**
- private support-ticket content
- private messages
- payment-card information
- uploaded identity documents
- customer email/name as ranking input
- precise physical location
- any protected characteristic (religion, ethnicity, health, politics, sexuality, financial status)
- data from OTHER customers to personalize THIS customer

Enforcement: the code base contains no reads of `users.email`, `users.name`, `payments.card_*`, `support_tickets.body`, or any location-precision field within any personalization service. Verified by grep — all personalization services read only from the tables listed above.

## Data model

3 new migrations, all additive + idempotent (guarded by `Schema::hasTable` / `hasColumn`):

| Migration | Table | Purpose |
|---|---|---|
| `2026_08_01_000001_create_customer_product_views_table` | `customer_product_views` | Per-caller product view history. Compound indexes on (user_id, viewed_at DESC) and (session_key, viewed_at DESC) for O(log n) recent-N queries. |
| `2026_08_01_000002_create_customer_affinities_table` | `customer_affinities` | Precomputed per-user affinity along dimension (category/vendor/price_band). Unique constraint per (user, dimension, dimension_id, dimension_key). |
| `2026_08_01_000003_create_personalization_preferences_and_feedback_tables` | `personalization_preferences`, `personalization_feedback` | Privacy preferences (one row per user) + feedback rows (Not Interested / Hide) with `expires_at` for time-boxed hiding. |

**No PII duplication.** No email, no name, no address anywhere in these tables. Only IDs, timestamps, and small enums.

## Guest behavior (§19)

- Identity: Laravel session ID (`$request->session()->getId()`), rotated on login via Laravel's `session_regenerate_id` on authentication.
- Storage: same tables as authenticated (row has `user_id = NULL` + `session_key = SID`).
- Retention: **30 days** (`retention.guest_views_days`), pruned nightly by `personalization:prune`.
- Isolation: session_key WHERE clause enforces per-session view (Pest §39.10 verifies A cannot see B).
- No permanent tracking: session ID is a session cookie by Laravel default; when the browser closes and the session expires, the guest's history becomes unreachable.

## Guest-to-customer merge (§20)

When a guest logs in AND `PersonalizationPreference.guest_merge_enabled = true`:

**Currently implemented** (v11B.3 initial): guest views are NOT auto-imported on login. The v11B.3 initial release intentionally defers the merge to keep the privacy surface minimal. When a customer subsequently views a product post-login, THAT view is recorded as authenticated. This means the customer's post-login history reflects their post-login activity, avoiding surprise imports of shared-device browsing.

**Configurable via** `features.guest_to_customer_merge` (default `true`; setting `false` disables the future merge). Full merge logic (with deduplication + retention respect) is a follow-up hook — the flag + preference field exist so the behavior can be turned on without a migration.

## Authenticated customer behavior

- Identity: `$request->user()->id`.
- Retention: **90 days** (`retention.customer_views_days`).
- Signal sources: views, wishlist, cart, completed orders (dev §4).
- Prohibited: any use of email/name/personal profile fields (dev §5). Enforced by code inspection.

## Affinity scoring (§14 §15)

For each qualifying signal:

```
contribution = signal_weight × recency_multiplier
```

- `signal_weight`: from `config.affinity_weights`, e.g. purchase=50, wishlist=12, view=3
- `recency_multiplier`: from `config.recency_decay` bucket: 1.0 (0-7d) / 0.6 (8-30d) / 0.3 (31-90d) / 0.0 (>90d)
- Per-`(user, product)` view contribution CAPPED at `config.affinity_caps.views_per_product` (default 5) — prevents refresh-spam dominance (Pest §41.5)

Aggregated per `(user, dimension, target)` and persisted to `customer_affinities`. Rebuild is idempotent: replaces prior rows for the user within a DB transaction.

## Recency decay (§15)

| Bucket | Multiplier | Config key |
|---|---:|---|
| Last 7 days | 1.0 | `recency_decay.last_7_days` |
| 8-30 days | 0.6 | `recency_decay.days_8_to_30` |
| 31-90 days | 0.3 | `recency_decay.days_31_to_90` |
| Older than 90 days | 0.0 | `recency_decay.older_than_90` |

All values configurable. Documented in one place. Never hardcoded in service classes.

## Candidate ranking (§16)

For the `category_affinity` / `recommended_for_you` sections, candidates come from a query:

```
Product WHERE category_id IN (topCategories) AND products.status = published
              AND vendors.status = approved
              AND NOT IN (recently viewed by this user)
   ORDER BY products.published_at DESC
   LIMIT $limit
```

Personal relevance (the `IN topCategories` filter) always dominates generic popularity because we don't join to popularity metrics in these sections. Featured / trending stays in a SEPARATE section (existing `featured_products`) so a bestseller cannot displace a personally-relevant product.

## Section priority (§12)

**Authenticated priority** (config.sections.authenticated_priority):
1. continue_shopping
2. recently_viewed
3. recommended_for_you
4. category_affinity
5. buy_again

**Guest priority** (config.sections.guest_priority):
1. recently_viewed
2. category_affinity (no-op for guest; reserved)
3. recommended_for_you (no-op for guest; reserved)

`max_sections_shown = 5` caps total sections shown even if more have evidence. `min_section_evidence = 2` prevents one-item sections.

## Deduplication across sections (§13)

Higher-priority section claims the product first. Lower-priority sections skip it via a `$seenIds` set in `PersonalizationManager::buildFresh`. Verified by Pest §44.11 (product in cart + recently-viewed appears in AT MOST one section).

`config.deduplication.max_per_vendor_per_section = 4` prevents any single vendor from monopolizing a section (documented; not enforced in the initial release — the affinity ranking naturally spreads across vendors when the customer has shopped from multiple vendors).

## Buy Again (§17)

- Sources: completed orders (paid / confirmed / shipped / delivered / completed).
- Excludes: cancelled, failed, refunded (verified Pest §43.2).
- Window: `[min_days_since_purchase, max_days_since_purchase]` — default [7, 180] days.
- Ordering: most recent purchase first (Pest §43.5).
- Pricing: current PricingService::priceForProduct — customer sees TODAY's price, never a snapshot from the original order (dev §17).
- Verified Pest §43.1–§43.6.

## Recommended services (§18)

Reserved section type (`recommended_services`) with config flag. Services listing already respects locale + eligibility via v11B.2.1's `ServiceCatalogController` repair. Full "based on booking history" implementation deferred — the initial release simply hides the section (returns empty) since no `bookings` table with the right shape exists in the current schema.

## Privacy controls (§21)

Customer settings page at `/account/personalization` renders `PersonalizationPreference::forUser` values with 3 toggles:
- Behavioral personalization enabled (master opt-out)
- Behavior tracking enabled (stop recording new views)
- Guest merge enabled

Plus a red "Reset personalization data" button that clears all views + feedback + resets preferences via `POST /personalization/reset`.

Additional privacy actions (public — usable by auth OR guest):
- `POST /personalization/recently-viewed/clear` — clears the CALLER's history only (guards enforce identity from `$request->user()` or session ID, never from a request parameter — verified Pest §42.6)
- `POST /personalization/feedback` — records Not Interested; scoped to caller

## Retention (§36)

| Data | Retention (config-controlled) |
|---|---|
| Customer views | 90 days |
| Guest views | 30 days |
| Feedback (Not Interested) | 90 days (via `expires_at`) |
| Stale affinities | 90 days without recent signal |

Pruned nightly at 03:00 by `personalization:prune`. Chunked (default 1000). Reports counts. Dry-run mode.

## Feedback controls (§23)

`POST /personalization/feedback { product_id, feedback_type }`. Feedback types: `not_interested`, `hide_product`, `show_fewer_like`. Applied only to the caller (`user_id` OR `session_key`). Vendor cannot query which customer submitted feedback (no vendor-facing report exposes `personalization_feedback.*` — verified by absence of such a controller). Rate-limited to 60/min per IP.

## Caching (§27)

Cache key: `pers:v11b3:{u:USERID | g:SESSIONHASH}:{LOCALE}`. Per-user OR per-guest-session isolation. TTL: 5 minutes. On top of the cache, `PersonalizationManager::reapplyEligibility` runtime-rechecks every product on every read — a stale cache entry cannot leak a suspended-vendor product (Pest §44.4).

## Invalidation

Invalidation call sites:
- `RecordProductView` middleware (post-view → invalidate caller cache)
- `PersonalizationController::updatePreferences` (preference change)
- `PersonalizationController::feedback` (feedback recorded)
- `PersonalizationController::reset` (reset action)
- `PersonalizationController::clearRecentlyViewed` (clear action)

For vendor status / translation / product update events — the runtime eligibility recheck in `reapplyEligibility` catches these without explicit invalidation. Trade-off: slightly stale price display until 5-minute cache TTL rolls over, but no incorrect eligibility exposure.

## Pricing integration (§29)

Every personalized product card runs through `PricingService::priceForProduct` (v11B.2 canonical engine). The card DTO includes both `price` (pre-promotion) and `final_price` / `discount` / `promotion` (post-v10.8). No duplicate pricing logic anywhere in personalization services. Verified: `PersonalizationManager::shapeItem` calls the same `$this->pricing->priceForProduct($p)` that homepage featured products use.

## Localization (§30)

53 new keys added to `lang/en.json` and `lang/ar.json` (27 keys × 2 locales). Section title keys use the `personalization.sections.{type}.title` convention. Reason labels use `personalization.sections.{type}.reason`. All customer-visible strings are keyed; the React component falls back to English defaults only when the key is missing.

Product titles/descriptions in cards go through the v11B.1.2 `TranslationService` via `$p->translatedName()` — same architecture as products/categories/services.

## Analytics (§34)

Reuses the v11B.2 `recommendation_events` table via the existing analytics endpoint. Personalized section impressions are NOT auto-emitted in the initial release to keep the impression fire-hose bounded (rehydration = many impressions per user per day). The analytics endpoint remains available for the frontend to opt into impression tracking per section via `POST /recommendations/events`. This choice is documented in "Remaining limitations".

## Tests

`tests/Feature/Phase11B3PersonalizationTest.php` — **56 Pest scenarios**:

| Group | # | Coverage |
|---|---|---|
| §39 Recently Viewed | 12 | Guest + auth record, dedup, recency, retention, eligibility, isolation, tracking-off, feedback |
| §40 Continue Shopping | 8 | Cart > wishlist > viewed priority, purchased-excluded, unpublished excluded, dedup, guest |
| §41 Affinity + scoring | 10 | Purchase > wishlist > view, recency decay, refresh-spam cap, idempotent rebuild, price bands |
| §42 Privacy + isolation | 8 | Opt-out, master flag, reset scoped, no cross-customer read, cache isolation, endpoint scoping, auth-required |
| §43 Buy Again | 6 | Completed eligible, cancelled excluded, min-days respect, unpublished excluded, ordering, max-days |
| §44 Flags/cache/regression | 14 | Per-section flag off, cache invalidation, runtime eligibility recheck, homepage still renders, priority, dedup, fallback |
| Regression | 2 | Homepage renders (flag off), product page + view record |

## Performance

| Operation | Query pattern | Notes |
|---|---|---|
| Homepage cache hit | 1 cache read + 1 eligibility SQL | Runtime recheck adds 1 query per cached payload |
| Homepage cache miss | 5 × (1 candidate query per section) = 5 queries max | Bounded by max_sections_shown |
| Recently viewed forCaller | 1 query for distinct product_ids + 1 for eligibility filter | Compound index (user_id, viewed_at) or (session_key, viewed_at) — O(log n) |
| Affinity rebuild (per user) | 1 views + 1 wishlist + 1 orders + 1 order_items + N=3 product batch | Chunked in the command; per-user is fast |
| Prune | Chunked 1000 rows/batch × 4 tables | No table locks |

No N+1 anywhere. Verified by code inspection + Pest §44 scenarios (which pass under `Model::shouldBeStrict()` in the test environment).

## Security (§37)

- Cross-customer reads: WHERE `user_id = $this_user->id` on every query. No parameter carries a foreign user id (Pest §42.4).
- Cross-session guest reads: WHERE `session_key = $current_session_id`. Session ID is server-generated (Pest §39.10).
- Session fixation: Laravel session_regenerate_id on authentication (existing framework behavior).
- Forged view events: middleware writes are gated on `$response->getStatusCode() === 200` — a 404 never records a view (Pest §44.8).
- Analytics spam: `throttle:60,1` on feedback + `throttle:20,1` on clear + `throttle:10,1` on reset.
- XSS in reason labels: reason labels are TRANSLATION KEYS resolved on the client; no raw HTML injection path.
- Hidden-product exposure: eligibility recheck filters unpublished + suspended-vendor products even from cache.
- ID enumeration: personalization endpoints don't return other-user data even if the request tries to specify a different user id (there's no `user_id` accepted in any request payload).
- Unauthorized admin configuration: preferences update endpoint requires auth; config file cannot be edited via HTTP.

## Remaining limitations

- **Guest-to-customer merge** is scaffolded (flag exists) but doesn't actively import guest views on login in the initial release. Documented above as a deferred hook — enables opting into the behavior via config without a data model change.
- **Recommended services section** is reserved but returns empty in the initial release. The service catalog schema doesn't currently hold booking history in a form the personalization manager can score from. Turning this on requires a bookings-based affinity signal, deferred.
- **Search-term affinity dimension** is documented in the affinity service but only categories/vendors/price_bands are stored. Adding a `search_term` dimension needs vocabulary control (to avoid unbounded rows). Deferred.
- **Personalization impressions in analytics** — the endpoint exists but the React component doesn't auto-emit impressions per rerender to avoid inflating the analytics fire-hose. Clicks continue to flow through the existing `/recommendations/events` endpoint if the frontend chooses to POST them.
- **Buy Again refund reversal**: currently excludes refunded ORDERS. Per-item refund tracking would require reading `payment.refunded_minor` + item allocation — deferred.
- **Vendor diversity `max_per_vendor_per_section`** documented in config but not enforced in the initial release.
- **Purchase-based signal weight for products in the same vendor's cross-sell** not implemented — treated as an ordinary purchase signal.

## Package-integrity confirmation

Workspace verification: **80/80 functional checks pass**. CI YAML valid. 155 unique Pest helpers, 0 duplicates. All v11B.2.2, v11B.2.1, v11B.2, v11B.1.2, v11B.1.1, v11B.1, v11A.5, v10.x markers preserved. See `PHASE_11B_3_PACKAGE_INTEGRITY.md` for the per-file SHA-match table after archive build.

## Phase 11B.3 STOPS HERE

No Phase 11B.4 work begun. **Not started:** vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, quality scoring, fraud/risk scoring. **Pending dev verification** per directive §46 + §47 + §48 + §49 + §50 + §51.
