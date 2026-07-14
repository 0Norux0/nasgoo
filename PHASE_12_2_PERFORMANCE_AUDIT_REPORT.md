# Phase 12.2 — Performance Audit Report

## Sandbox honesty note

I cannot measure page load times from this sandbox (no PHP, no server, no browser). This report is a **template** the developer fills in with real numbers after deploy — NOT fabricated measurements. Every "Response Time" and "Query Count" column is `PENDING` until the operator runs the measurements.

The static parts I CAN provide: which pages to test, which optimizations are already in place from prior phases, and what "unhealthy" numbers to watch for.

## How to measure

### Option 1: Laravel Debugbar (staging only)

```bash
composer require barryvdh/laravel-debugbar --dev
```

Then load a page and inspect the "Queries" tab. Note: never install Debugbar on production — it's an information disclosure risk.

### Option 2: Laravel Telescope (staging only)

Same trade-off.

### Option 3: New Relic / Datadog / Blackfire (production-safe)

APM tools designed for production. Show real user timings + query breakdowns. Blackfire has the best PHP profiling.

### Option 4: Server-side timing headers

Add `Server-Timing` headers via a middleware to get browser DevTools "Timing" tab data without third-party tools. Simple and free.

### Option 5: `EXPLAIN` on slow queries

Once you know a query is slow (via MySQL slow query log), get its query plan:

```sql
EXPLAIN SELECT ... FROM ... WHERE ...;
```

Look for `type: ALL` (full table scan — bad on large tables) or `rows: 100000+` (index missing or ineffective).

## Enable MySQL slow query log

Add to `my.cnf`:

```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.5      # log anything > 500ms
log_queries_not_using_indexes = 1
```

Then use `pt-query-digest` from Percona Toolkit:

```bash
pt-query-digest /var/log/mysql/slow.log | less
```

Top-N slowest queries surface immediately.

## Pages to audit (public)

Fill in Response Time (ms) + Query Count for each. Target: response ≤ 200 ms, queries ≤ 20 per page (aggressive but achievable with the current indexing).

| Page | URL pattern | Response time | Query count | Notes |
| --- | --- | --- | --- | --- |
| Homepage | `/` | PENDING | PENDING | Uses `SiteSettingsService` cache — should be fast |
| Product listing | `/products` | PENDING | PENDING | Uses `search_queries` indexes (Phase 6) |
| Product detail | `/products/{slug}` | PENDING | PENDING | Loads product + variants + images + reviews |
| Product search | `/search?q=...` | PENDING | PENDING | Meilisearch if configured, else DB fallback |
| Arabic search | `/search?q=...&locale=ar` | PENDING | PENDING | Uses `product_translations` |
| Vendor storefront | `/vendors/{slug}` | PENDING | PENDING | Vendor + products + reviews |
| Vendor products | `/vendors/{slug}/products` | PENDING | PENDING | Paginated |
| Services listing | `/services` | PENDING | PENDING | Filter by `type=service` |

## Pages to audit (customer, authed)

| Page | URL pattern | Response time | Query count | Notes |
| --- | --- | --- | --- | --- |
| Cart | `/cart` | PENDING | PENDING | Real-time totals via `PricingService` |
| Checkout | `/checkout` | PENDING | PENDING | Server-authoritative pricing (v11B.2.2) |
| Orders list | `/orders` | PENDING | PENDING | Paginated with `orders_status_created_at_idx` |
| Order detail | `/orders/{id}` | PENDING | PENDING | Loads order + items + events |
| Bookings | `/bookings` | PENDING | PENDING | Uses `service_bookings` composite indexes |
| Support tickets | `/support` | PENDING | PENDING | Uses `(user_id, status, created_at)` index |

## Pages to audit (vendor, authed as approved vendor)

| Page | URL pattern | Response time | Query count | Notes |
| --- | --- | --- | --- | --- |
| Dashboard | `/vendor` | PENDING | PENDING | Widgets use `Cache::remember` (Phase 11B.3.2) |
| Products list | `/vendor/products` | PENDING | PENDING | `(vendor_id, status)` index |
| Product edit | `/vendor/products/{id}/edit` | PENDING | PENDING | Includes quality badge from `vendor_product_quality_scores` |
| Orders list | `/vendor/orders` | PENDING | PENDING | `(vendor_id, fulfillment_status)` on order_items |
| Reports | `/vendor/reports` | PENDING | PENDING | Includes `VendorReportsIntelligenceEmbed` (v11B.4.2) |
| Intelligence | `/vendor/intelligence` | PENDING | PENDING | Cached via VendorIntelligenceCacheService |
| Settings | `/vendor/settings` | PENDING | PENDING | Simple form load |

## Pages to audit (admin, authed as super_admin)

| Page | URL pattern | Response time | Query count | Notes |
| --- | --- | --- | --- | --- |
| Dashboard | `/admin` (Filament) | PENDING | PENDING | Widgets use cache |
| Products | `/admin/products` (Filament) | PENDING | PENDING | Paginated Filament resource |
| Vendors | `/admin/vendors` (Filament) | PENDING | PENDING | Paginated |
| Customers | `/admin/customers` (Filament) | PENDING | PENDING | Paginated |
| Orders | `/admin/orders` (Filament) | PENDING | PENDING | Paginated |
| Reports | `/admin/reports` | PENDING | PENDING | Aggregate queries |
| Site settings | `/admin/site-settings` | PENDING | PENDING | Small payload (settings JSON) |
| Translations | `/admin/translations` (Filament) | PENDING | PENDING | Uses `(product_id, locale, status)` index |
| Vendor intelligence overview | `/admin/vendor-intelligence` | PENDING | PENDING | Aggregate from `vendor_intelligence_summaries` |

## What "unhealthy" looks like

Investigate any of these:

- **Response time > 500 ms** for a public page (customers give up)
- **Response time > 2 s** for an admin page (annoying but tolerable)
- **Query count > 50** on a single page (usually N+1)
- **Query count grows with input** (e.g. product list shows 20 products but issues 100 queries — that's classic N+1: 1 for products + N for each product's images + N for each product's category + ...)
- **Same query repeated multiple times** — missing eager-loading

## Optimizations already in place (from prior phases)

Verified from migrations and code:

- **77 composite/unique indexes** on major tables — full audit in `PHASE_12_DATABASE_READINESS_REPORT.md` §9
- **`SiteSettingsService` cache** — grouped `Cache::remember` with per-group invalidation
- **`VendorIntelligenceCacheService`** — dashboard payload cached for `cache_ttl` seconds (default 900 = 15 min)
- **Phase 11B.3.2 `StatsOverview` cache** — admin dashboard widgets memoized
- **Phase 10 v10.1 + v10.14 index migrations** — targeted admin-panel indexes
- **Phase 6 search performance indexes on products** — `2026_06_25_000004_add_search_performance_indexes_to_products.php`
- **`recommendations:generate --since=2` daily** — precomputes recommendations, keeps `/products/{id}` fast
- **`personalization:rebuild --stale-days=1` daily** — precomputes customer affinities, keeps `/` recommendations fast

## Common N+1 hotspots to eager-load

If new N+1s appear post-deploy, check these first:

- `Product::with(['images', 'category', 'vendor', 'variants'])` — needed on product list pages
- `Order::with(['items.product', 'items.vendor', 'user', 'shipping_address'])` — needed on order list pages
- `Vendor::with(['user', 'subscription'])` — needed on vendor list pages
- `Alert::with(['vendor'])` — needed on `/admin/vendor-intelligence`

Search app code:

```bash
grep -rn "Product::\|Order::\|Vendor::" app/Http/Controllers/ | grep -v "with(" | head -20
```

Any query in a loop without `->with()` is a potential N+1.

## Payload size

For pages returning lots of data via Inertia:

```bash
# Check response size (compressed)
curl -sIH "Accept-Encoding: gzip" https://YOUR_DOMAIN/some-page | grep -i content-length
```

Target: ≤ 100 KB compressed for a listing page, ≤ 500 KB for a heavy admin page.

If payloads are huge, look for:

- Loading full-text description fields in listing pages (use `->select(...)` to trim)
- Loading `array_merge($items, $categories, $tags, $filters, $breadcrumbs, $recommendations, $notifications)` all at once — split into separate requests where possible
- Loading nested relationships with unused fields

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| 77 indexes across major tables | ✅ | Migration audit in `PHASE_12_DATABASE_READINESS_REPORT.md` §9 |
| Scheduled recommendation regeneration | ✅ | `grep "recommendations:generate" routes/console.php` |
| SiteSettingsService uses Cache::remember | ✅ | `grep "Cache::remember" app/Services/Settings/SiteSettingsService.php` |
| Actual page timings measured | ⏳ | Operator uses Debugbar / Telescope / APM / EXPLAIN |
| MySQL slow query log enabled | ⏳ | Operator adds `slow_query_log = 1` to my.cnf |
| No N+1 in production hot paths | ⏳ | Operator identifies via APM |
| Payload sizes under target | ⏳ | Operator measures via `curl -sI` |
