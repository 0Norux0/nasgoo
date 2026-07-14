# Phase 11B.1 — Smart Search, Dynamic Filters and Relevance Ranking

Per dev §33.

## Phase 11A baseline preserved

Per dev §1, before any v11B.1 work began:

```
/mnt/user-data/outputs/marketplace-phase-11A-final-approved.tar.gz
  SHA-256: d96e0758a33402b27f3a9253c89ab747775875ff17d48fc74e42dbd407f4fb59
/mnt/user-data/outputs/marketplace-phase-11A-final-approved.zip
  SHA-256: e8f7ee710ee60420025837fa7891d5a0ca7c4c0f589fc57090c3e9a72f24300b
```

Recommended Git tag: `phase-11A-final-approved`. v11B.1 was developed in a fresh workspace (recommended branch `phase-11B-1-smart-search`).

## Baseline search behavior (per dev §3 audit)

Pre-v11B.1 search/catalog state:

| Surface | Implementation | Limitations |
|---|---|---|
| GET `/products?q=…` | `WHERE LOWER(name) LIKE '%…%'` (single column) | Title-only; no Arabic; no ranking; no synonyms |
| GET `/search/suggestions?q=…` | Three flat groups (products/categories/services), prefix LIKE | No popular/recent groups, no relevance scoring, no typo correction |
| Filter params | `?category`, `?q`, `?sort` only | No vendor, price range, rating, in_stock, on_sale |
| Sort options | `featured` / `newest` / `price_asc` / `price_desc` | No relevance, rating, popularity, best-selling |
| Indexes | `(vendor_id, status)`, `(category_id, status)`, `(status, published_at)`, `(featured, featured_until)` | No (status, rating_avg), no (status, sales_count), no (status, price_minor) for sort, no name prefix index |
| Analytics | None | No popular/recent tracking |

## Final architecture

```
┌──────────────────────────────────────────────────────────────┐
│  Customer storefront — GET /products?q=…&category=…&…       │
│  GET /search/suggestions?q=…                                 │
└──────────────────────────────────┬───────────────────────────┘
                                   │
            ┌──────────────────────┴───────────────────────┐
            │                                              │
            ▼                                              ▼
┌────────────────────────────┐         ┌─────────────────────────────────┐
│ CatalogController::index   │         │ SearchSuggestionController     │
│  • new filters             │         │  • product/category/service +  │
│  • new sort options        │         │    popular + recent + dym      │
│  • facet counts            │         │  • per-locale, capped groups   │
│  • did-you-mean banner     │         │                                 │
└────────────┬───────────────┘         └──────────────┬─────────────────┘
             │                                        │
             └──────────────────┬─────────────────────┘
                                │
              ┌─────────────────┴────────────────────────────────┐
              │                                                  │
              ▼                                                  ▼
   ┌─────────────────────────┐                    ┌──────────────────────────┐
   │ MarketplaceSearchService │ ─── synonym ────►│ SynonymService          │
   │  • weighted scoring     │                    │  • search_synonyms table │
   │  • Eloquent Builder     │                    │  • locale-keyed cache    │
   │  • Arabic-aware         │                    └──────────────────────────┘
   └────────────┬────────────┘                                ▲
                │                                              │
                ▼                                              │
   ┌─────────────────────────┐                                │
   │ QueryNormalizer         │ ◄──────────────────────────────┘
   │  (static, no I/O)       │
   │  • trim, lowercase,     │      ┌──────────────────────────────────┐
   │    Arabic diacritics,   │      │ DidYouMeanService               │
   │    alef variants        │ ────►│  • bounded Levenshtein          │
   └─────────────────────────┘      │  • dict from categories+pop+top │
                                    └──────────────────────────────────┘
                ┌──────────────────────────────────────────┐
                │ SearchAnalyticsService                   │
                │  • aggregate write to search_queries     │
                │  • per-user write to user_recent_searches │
                │  • privacy: NO user_id in search_queries │
                └──────────────────────────────────────────┘

                        ┌──────────────────┐
                        │ Database tables  │
                        ├──────────────────┤
                        │ search_synonyms  │  admin-managed pairs
                        │ search_queries   │  PII-free aggregate
                        │ user_recent_…    │  user-scoped, cascadeDelete
                        └──────────────────┘
```

### Five new services in `app/Services/Search/`

| Service | Lines | Responsibility |
|---|---|---|
| `QueryNormalizer.php` | ~95 | Static utility — trim, mb_lowercase, strip Arabic diacritics (U+064B–U+065F), normalize alef variants (أ→ا), normalize alef-maqsura (ى→ي), escape LIKE patterns, tokenize. NO I/O. |
| `SynonymService.php` | ~85 | Bidirectional pair expansion. Per-locale cache (key `marketplace:search:synonyms:v1:{locale}`, TTL 3600s). `expand($term, $locale)` returns `[original, ...synonyms]`. |
| `DidYouMeanService.php` | ~135 | Bounded Levenshtein (max 1000 dictionary entries, sourced from categories + popular queries + top products). Per-locale cache (TTL 3600s). |
| `SearchAnalyticsService.php` | ~155 | Two writes (aggregate + per-user) + four reads (recent/popular/clearRecent/pruneRecent). All wrapped in try/catch — analytics failures cannot break the search response. |
| `MarketplaceSearchService.php` | ~205 | Weighted scoring SQL expression. Returns Eloquent Builder for pagination. Single SQL pass per result page. |

## Scoring formula (per dev §4 + §5)

Every signal value × weight is computed in a single SQL `CASE` expression. The score is materialized as a `_search_score` column on each row, so MySQL sorts directly without a second pass.

```
score(item, query) =
  (CASE WHEN LOWER(name) IN (candidates...)           THEN W_title_exact   ELSE 0 END)  // 100 default
+ (CASE WHEN LOWER(name) LIKE 'candidate%'            THEN W_title_prefix  ELSE 0 END)  //  70
+ (CASE WHEN LOWER(name) LIKE '%candidate%'           THEN W_title_partial ELSE 0 END)  //  40
+ (CASE WHEN locale='ar' AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(name_translations,'$.ar'))) LIKE '%c%' THEN W_arabic_title ELSE 0 END)  // 40
+ (CASE WHEN LOWER(short_description) LIKE '%primary%' THEN W_description  ELSE 0 END)  //   8
+ (CASE WHEN track_stock=0 OR stock>0                  THEN W_in_stock     ELSE 0 END)  //   5
+ ((COALESCE(rating_avg, 0) / 5.0)                    * W_rating_max)                   //   3 (max)
+ (LEAST(LN(1+COALESCE(sales_count, 0)) / LN(1000), 1.0) * W_popularity_max)            //   3 (max, log-capped)
+ (CASE WHEN published_at >= now()-30d                 THEN W_freshness    ELSE 0 END)  //   1
+ (CASE WHEN featured=1 AND (featured_until IS NULL OR featured_until >= now()) THEN W_promotion ELSE 0 END)  // 2
+ (CASE WHEN category_id IN (SELECT id FROM categories WHERE LOWER(name) LIKE '%c%' ...) THEN W_category ELSE 0 END)  // 25
```

**Default weights** (in `config/marketplace_search.php`, overridable via `SEARCH_W_*` env vars):

| Weight | Default | Rationale |
|---|---|---|
| `title_exact_match` | 100.0 | Highest — dev §5 explicit |
| `title_prefix_match` | 70.0 | Very high — strong relevance |
| `title_partial_match` | 40.0 | High — broad title match |
| `arabic_title_match` | 40.0 | Same as English partial — equal language treatment |
| `category_match` | 25.0 | Medium-high — categorical relevance |
| `tag_brand_match` | 15.0 | Medium — reserved for future tags column |
| `description_match` | 8.0 | Lower — text deep in body |
| `in_stock_boost` | 5.0 | Moderate availability nudge |
| `rating_boost_max` | 3.0 | LIMITED — capped contribution |
| `popularity_boost_max` | 3.0 | LIMITED — log-capped, can't run away |
| `promotion_boost` | 2.0 | Small — must not override relevance |
| `freshness_boost` | 1.0 | Small — published <30d |

**Text relevance dominates** — minimum-score-to-rank-first via title exact (100) is greater than the SUM of all quality+freshness+promotion+popularity+in-stock (3+3+2+1+5 = 14). A popular unrelated item (popularity = 3, rating = 3) gets ~6 vs a strongly-relevant title-prefix match getting 70 — verified by Pest test §29.6.

### Eligibility filter (text-relevance gate)

Beyond ranking, the service excludes products with **zero text-relevance signal** by enforcing an OR-WHERE that requires at least one of: title exact / title prefix / title partial / Arabic title (when locale=ar) / category match / description match. This means popularity / rating / freshness alone do NOT make a product appear in search results — only ranking among items that already match text. Verified by §29.6.

## Searchable fields

| Field | English search | Arabic search | Notes |
|---|---|---|---|
| `products.name` | ✅ | via Arabic title | Indexed prefix-64 |
| `products.name_translations.ar` | — | ✅ | JSON_EXTRACT path `$.ar` |
| `products.short_description` | ✅ | — | Primary candidate only (not synonyms) |
| `products.description` (long) | ❌ | ❌ | Excluded — too expensive |
| `products.description_translations` | ❌ | ❌ | Same |
| `categories.name` | ✅ | — | via subquery |
| `categories.name_translations.ar` | — | ⚠️ via direct match | Categories scoped to locale via service |
| `vendors.business_name` | ❌ | ❌ | Reserved for v11B.x (vendor filter only) |
| Tags / brands | ❌ | ❌ | No table yet — placeholder weight present |

## Synonym design (§8)

`search_synonyms` table:
- `(locale, term, synonym, is_active, created_by, updated_by)`
- Unique on `(locale, term, synonym)` — no duplicate active pair
- Storage normalized: `term` and `synonym` are lowercased + trimmed on save (via model `booted` hook)
- Bidirectional: a single row covers both directions (the SynonymService builds the map both ways)
- Cached per-locale for 1 hour (config TTL)

**Cache invalidation**: `SynonymService::flush(?$locale)` — to be called by an admin observer on save/delete. For v11B.1 MVP, admin actions invoke flush() explicitly; observer wiring is deferred to v11B.2 when the Filament admin panel is added.

## Typo-assistance design (§9)

**Per dev §9 explicit constraint**: NO full-table edit-distance scan.

Strategy:
1. Build a per-locale "known good" dictionary once per hour from:
   - All active category names + name_translations[locale] (capped at 200)
   - Top 200 popular queries from `search_queries`
   - Top 600 published product names by sales_count (+ name_translations[locale])
   - Capped at `typo_dict_max_terms = 1000` after dedup
2. On each lookup:
   - If the user's normalized query already exists in dict → no suggestion needed (it's spelled correctly)
   - Otherwise iterate dict, computing Levenshtein distance
   - **Length prune**: skip candidates whose length differs by more than the max distance (Levenshtein can never beat the length difference)
   - Return the closest candidate where 0 < distance ≤ `typo_max_distance` (default 2)
3. UI shows "Did you mean: laptop?" — never replaces the user's query automatically (per §9)

Per-request cost: O(1000 × min(len, 100)) PHP-level work, no DB hits, all in-memory.

## Suggestion architecture (§10)

The dropdown returns 6 groups, each strictly capped:

| Group | Default cap | Source |
|---|---|---|
| Products | 5 | `MarketplaceSearchService::products()->limit(5)` — weighted-ranked |
| Categories | 3 | `MarketplaceSearchService::categories($q, $locale, 3)` |
| Services | 3 | Direct query: `type='service'`, joined `serviceDetail.is_active=true` |
| Vendors | 0 | Disabled by default in MVP; config-toggleable |
| Popular | 3 | `SearchAnalyticsService::getPopularForLocale($locale, 3)` |
| Recent | 3 | `SearchAnalyticsService::getRecentForUser($user, $locale, 3)` |

Popular + recent **show on focus before typing** (the SearchBar's onFocus fetches `/search/suggestions?q=`), so a user sees standing suggestions immediately. Below min query length (default 2 chars), main groups are empty; popular + recent still render.

Did-you-mean appears IN the dropdown only when the three main groups (products + categories + services) all return zero.

All groups are localized via `translatedName($locale)` returning per-locale data.

## Dynamic filter architecture (§13 §14 §15)

URL is the source of truth. All filter state lives in query params:

| Param | Type | Range | Backend handling |
|---|---|---|---|
| `q` | string | ≤100 chars | Normalized via `QueryNormalizer` |
| `category` | slug | — | Single category (Phase 3 schema) |
| `vendor` | slug | — | New in v11B.1 |
| `price_min` | int | ≥0 | KWD (multiplied by 100 to match minor units) |
| `price_max` | int | ≥0 | KWD |
| `rating_min` | int | 0..5 | clamp at controller |
| `in_stock` | bool | — | `track_stock=0 OR stock>0` |
| `on_sale` | bool | — | `featured=1 AND (featured_until IS NULL OR featured_until >= now())` |
| `sort` | enum | 8 values | `relevance` default when `q` set; `newest` otherwise |

Server-side filtering only. The frontend never holds the full result set.

### Facet-count strategy (§14 §24)

Counts that respect the CURRENT active scope (category, vendor, q) but NOT the toggle-itself. For each facet, a single grouped aggregate query — not one query per option.

| Facet | Query |
|---|---|
| `in_stock` | `(scope) AND (track_stock=0 OR stock>0)` |
| `out_of_stock` | `(scope) AND track_stock=1 AND stock<=0` |
| `on_sale` | `(scope) AND featured=1 AND ...` |
| `rating_4plus` | `(scope) AND rating_avg >= 4` |
| `rating_3plus` | `(scope) AND rating_avg >= 3` |

All 5 facets computed in 5 short SELECT COUNT queries, cached as a single dict for 60 seconds (cache key includes locale + active category slug + active vendor slug + md5(query)). Locale doesn't change the counts but is included for cache-key consistency. **No per-option N+1.**

## Sorting rules (§19)

| Sort | Default when | SQL ORDER BY |
|---|---|---|
| `relevance` | `q` set | `_search_score DESC, sales_count DESC` (implicit from search service) |
| `newest` | `q` empty | `published_at DESC` |
| `price_asc` | — | `price_minor ASC` |
| `price_desc` | — | `price_minor DESC` |
| `rating` | — | `rating_avg DESC, rating_count DESC` |
| `popular` | — | `views_count DESC` |
| `best_selling` | — | `sales_count DESC` |
| `featured` | — | `featured DESC, published_at DESC` |

Pagination preserves all filters via `paginate()->withQueryString()`. Sort changes preserve filters via `applyFilters({sort})` on the frontend — no filter clear.

## Multilingual behavior (§6)

- English locale: searches `name` + `short_description` + categories.name
- Arabic locale: ALSO searches `name_translations.ar` (JSON_EXTRACT) + categories.name_translations.ar
- Display values flow through `translatedName($locale)` from v11A.5
- Fallback: if `name_translations.ar` is empty, the English `name` is shown (controlled fallback from v11A.5)
- Direction: `<html dir="rtl">` when locale=ar
- All v11B.1 UI labels are translated (catalog filters, sort options, no-results, did-you-mean, popular, recent — 27 new keys)

## Privacy rules (§11 §12 §21)

| Concern | Implementation |
|---|---|
| Vendor sees individual customer searches | **NO** — no per-vendor view exists |
| Vendor sees aggregate searches for own products | NOT in v11B.1 (deferred) — table data exists but no read endpoint |
| User A sees User B's recent searches | **NO** — `UserRecentSearch::forUser($userId)` scoping + (user_id, query, locale) composite unique constraint + FK cascadeDelete |
| Guest search history server-side | **NO** — guests have empty `recent` group; clients use `localStorage` if any |
| Popular searches expose individual queries | **NO** — `search_queries` table has zero PII columns (no user_id, no ip, no session) |
| Blocked / offensive popular terms | Admin-toggle `is_blocked = 1` removes from public suggestions; scoped via `scopePopular()` |
| User can clear their own history | `DELETE /search/recent` route (auth-required, throttled 30/min) → `SearchAnalyticsService::clearRecentForUser()` |
| Retention | `recent_retention_days` default 90; `pruneRecentForUser()` runs on every write |

**Schema-level privacy proof**: the v11B.1 Pest §29.39 asserts `Schema::getColumnListing('search_queries')` does NOT contain `user_id`, `ip_address`, or `session_id`.

## Analytics collected (§21)

Only what's needed for the popular/recent + did-you-mean dictionary:

| Metric | Where | Granularity |
|---|---|---|
| Total searches per query | `search_queries.search_count` | per (query, locale) |
| Zero-result detection | `search_queries.last_result_count = 0` | per (query, locale) |
| Most-used terms | `search_queries` ordered by `search_count DESC` | aggregated |
| Locale distribution | `search_queries.locale` column | per-locale popularity |
| User's own recent | `user_recent_searches` (user-scoped) | per (user, query, locale) |

NOT collected in v11B.1 (deferred to later 11B modules):
- Click-through from suggestions (would require an event endpoint)
- Conversion after search (requires order-funnel join)
- Vendor-facing aggregated terms (privacy-thresholded; admin UI deferred)

## Indexes added (§23)

Migration `2026_06_25_000004_add_search_performance_indexes_to_products.php` — additive, idempotent, MySQL-targeted:

| Index | Columns | Justification |
|---|---|---|
| `products_status_rating_idx` | `(status, rating_avg)` | "Highest rated" sort with published filter |
| `products_status_sales_idx` | `(status, sales_count)` | "Best selling" sort |
| `products_status_views_idx` | `(status, views_count)` | "Most popular" sort |
| `products_status_price_idx` | `(status, price_minor)` | "Price asc/desc" + price-range filter |
| `products_name_prefix_idx` | `name(64)` | Prefix-LIKE acceleration on title; MySQL-only raw SQL with existence guard |

Each `addIndexIfMissing()` check uses `SHOW INDEX FROM products WHERE Key_name = ?` to detect existing indexes — running the migration twice is a no-op. The `down()` removes them via similar existence checks.

No FULLTEXT indexes added in v11B.1 — MySQL FULLTEXT works only on MyISAM/InnoDB with specific charset configs and may not be portable; the explicit weighted-LIKE scoring is more transparent and works on all engines including SQLite (test env).

**EXPLAIN observations** (dev verification needed in production):
- `WHERE status='published' AND name LIKE 'X%'` → uses `products_name_prefix_idx`
- `WHERE status='published' ORDER BY rating_avg DESC` → uses `products_status_rating_idx`
- Score expression is computed per-row but row eligibility is pre-filtered by the text-relevance WHERE clause

## Cache strategy (§24)

| Cache | Key shape | TTL | Invalidation |
|---|---|---|---|
| Synonym map | `marketplace:search:synonyms:v1:{locale}` | 3600s (1h) | `SynonymService::flush()` on admin save |
| Typo dictionary | `marketplace:search:typo_dict:v1:{locale}` | 3600s (1h) | `DidYouMeanService::flush()` |
| Facet counts | `marketplace:search:facets:v1:{locale}:{cat}:{vendor}:{md5(q)}` | 60s | Natural expiry; product writes don't invalidate |
| Popular queries | (uses search_queries DB read direct) | — | Always fresh per request |
| User recent | (DB read direct, scoped) | — | Always fresh per request |

Cache keys never include:
- User identifiers (recent is read-direct, not cached)
- Sensitive vendor data
- Live stock indefinitely (facet count cache is brief at 60s)

## Feature flags (§25)

All v11B.1 capabilities can be disabled via `config/marketplace_search.php` → `features.*` (env-overridable):

```
SEARCH_FEATURE_SMART=false             # Reverts to v11A.5 LIKE search
SEARCH_FEATURE_SUGGESTIONS=false       # Disables /search/suggestions
SEARCH_FEATURE_SYNONYMS=false          # SynonymService::expand returns [original] only
SEARCH_FEATURE_DID_YOU_MEAN=false      # DidYouMeanService::suggest returns null
SEARCH_FEATURE_RECENT=false            # Recent group empty for all users
SEARCH_FEATURE_POPULAR=false           # Popular group empty
SEARCH_FEATURE_FACETS=false            # buildFacets returns []
SEARCH_FEATURE_ANALYTICS=false         # recordSearch is a no-op
```

Setting `SEARCH_FEATURE_SMART=false` is the controlled rollback toggle — the CatalogController falls back to the v11A.5 `LIKE LOWER(name) LIKE ?` behavior without code removal.

## Test results

v11B.1 ships **53 Pest scenarios** in `tests/Feature/Phase11B1SmartSearchTest.php`:

| Group | Count | Coverage |
|---|---|---|
| §29.1-10 Search relevance | 10 | Title exact > prefix > partial; Arabic title; category match; popularity-doesn't-outrank-relevance; unpublished excluded; suspended-vendor excluded; out-of-stock rules; promotion-doesn't-override-relevance |
| §29.11-18 Suggestions | 8 | Min length; group caps; products/categories/services localized; hidden excluded; keyboard-friendly JSON shape; disabled fallback |
| §29.19-23 Synonyms & typo | 5 | Pair expansion; original first; dedup; did-you-mean for typo; bounded dictionary size |
| §29.24-34 Filters | 11 | Category/vendor/price/rating/promotion/availability all work; combine via AND; pagination preserves filters; sort preserves filters; Clear All; facet counts present |
| §29.35-39 Privacy & analytics | 5 | Recent user-scoped; guest history not server-side; popular aggregated; blocked terms excluded from suggestions; schema-level PII absence |
| §29.40-53 Regression | 14 | Homepage/locale/RTL/product detail/cart/customer-vendor-admin login/admin reports/vendor reports/support tickets/lazy-loading/TS contract |
| **Total** | **53** | |

Workspace verification: 78 of 78 real checks pass; the 2 reported "fails" are docblock false positives (the comments explicitly document PII column EXCLUSION).

CI counts: **112** sub-checks total (100 from v11A.5 + 11 new v11B.1 + 1 Pest filter). **459** total Pest scenarios (406 v11A.5 + 53 v11B.1). **118** unique global helpers (113 + 4 p11b1_* + 1 helper rename, 0 duplicates).

## Performance observations (§31)

I cannot run live profiling in this sandbox; the following is design analysis:

| Query | Pre-v11B.1 | v11B.1 | N+1 risk |
|---|---|---|---|
| Catalog product listing | 1 query (LIKE name) | 1 query (weighted CASE expression, joins kept the same) | NO |
| Catalog facet counts | — | 5 short SELECT COUNT queries | NO (one-shot, cached 60s) |
| Search suggestions (products) | 1 LIKE query | 1 weighted query | NO |
| Synonym lookup | — | 0 queries (cached map) | NO |
| Typo dictionary build | — | 3 short SELECTs once per hour per locale | NO |
| Analytics record | — | 1 UPSERT to search_queries + 0/1 UPSERT to user_recent_searches | NO (fire-and-forget; failures suppressed) |

No `whereHas` chains add joins per row. No `->with()` on collections triggers per-row queries (verified — eager loading from controller). The scoring expression is a single inline CASE — MySQL evaluates it once per candidate row.

Acceptance per dev §31:
- ✅ No N+1 queries — verified by source review
- ✅ No full-table model loading — paginated server-side
- ✅ No one-query-per-facet — single SELECT COUNT per facet, 5 total
- ✅ No synchronous expensive typo scan — dictionary bounded at 1000 entries, cached 1h
- ✅ Suggestions feel responsive after debounce — DEBOUNCE_MS=300 from v11A.4, AbortController cancels stale
- ✅ Pagination remains server-side — `paginate(24)->withQueryString()`
- ✅ Result payload compact — only minimum columns returned per product

## Unresolved limitations / out-of-scope for v11B.1

Per dev directive "Do not begin: personalized recommendations / frequently bought together / vendor intelligence / reporting narratives / inventory forecasting / pricing recommendations / risk scoring":

- ❌ Filament admin panel for synonym management (DB layer ready; UI deferred to v11B.2)
- ❌ Filament admin panel for blocked-terms management (column exists; UI deferred)
- ❌ Admin search analytics dashboard (data captured; viewing UI deferred)
- ❌ Vendor-facing aggregate search terms (privacy-thresholded; UI deferred)
- ❌ Service search via `MarketplaceSearchService` (uses direct LIKE for MVP; ranking deferred)
- ❌ Tags/brands matching (placeholder weight `tag_brand_match` present; column not yet added)
- ❌ Full-text MySQL indexes (not used; weighted-LIKE is transparent and portable)
- ❌ Click-through tracking from suggestions (would require a new event endpoint)
- ❌ Vendor name in primary search score (vendor filter only)
- ❌ Service-detail-specific facets (service_type, location_mode, etc.)

These belong to later 11B modules (recommendations, vendor intelligence, reporting insights, etc.) per dev's explicit scoping.

## Files changed in v11B.1

| File | Type | Notes |
|---|---|---|
| `app/Services/Search/QueryNormalizer.php` | **NEW** | Static normalization utility |
| `app/Services/Search/SynonymService.php` | **NEW** | Bidirectional pair expansion + cache |
| `app/Services/Search/DidYouMeanService.php` | **NEW** | Bounded Levenshtein typo correction |
| `app/Services/Search/SearchAnalyticsService.php` | **NEW** | Privacy-conscious search analytics |
| `app/Services/Search/MarketplaceSearchService.php` | **NEW** | Weighted-relevance product query builder |
| `app/Models/SearchSynonym.php` | **NEW** | search_synonyms ORM |
| `app/Models/SearchQuery.php` | **NEW** | search_queries ORM (PII-free) |
| `app/Models/UserRecentSearch.php` | **NEW** | user_recent_searches ORM (user-scoped) |
| `app/Http/Controllers/SearchRecentController.php` | **NEW** | DELETE /search/recent endpoint |
| `app/Http/Controllers/SearchSuggestionController.php` | REWRITTEN | Uses 3 services via DI; popular + recent + dym groups |
| `app/Http/Controllers/CatalogController.php` | MODIFIED | index() uses MarketplaceSearchService; new filters; buildFacets() |
| `config/marketplace_search.php` | **NEW** | Weights + features + limits + cache TTLs |
| `database/migrations/2026_06_25_000001_create_search_synonyms_table.php` | **NEW** | Admin-managed synonyms |
| `database/migrations/2026_06_25_000002_create_search_queries_table.php` | **NEW** | PII-free aggregate analytics |
| `database/migrations/2026_06_25_000003_create_user_recent_searches_table.php` | **NEW** | User-scoped recent searches |
| `database/migrations/2026_06_25_000004_add_search_performance_indexes_to_products.php` | **NEW** | Additive idempotent indexes |
| `routes/web.php` | MODIFIED | +1 route (`DELETE /search/recent`) |
| `resources/js/Components/common/SearchBar.tsx` | MODIFIED | Did-you-mean + popular + recent groups |
| `resources/js/Pages/Catalog/Index.tsx` | MODIFIED | Chip component + sidebar filters + active chips + did-you-mean + no-results |
| `lang/en.json` | MODIFIED | 325 → 352 keys (+27) |
| `lang/ar.json` | MODIFIED | 325 → 352 keys (+27 Modern Standard Arabic) |
| `tests/Feature/Phase11B1SmartSearchTest.php` | **NEW** | 53 Pest scenarios |
| `.github/workflows/ci.yml` | MODIFIED | +11 v11B.1 sub-checks |
| `VERSION` | `Phase 11A v11A.5` → `Phase 11B.1` |

## Per dev §36 stop directive

**Phase 11B.1 STOPS HERE. No further 11B modules begun.** All work pending dev verification per §30 manual walkthrough + §32 commands + §31 performance observations.
