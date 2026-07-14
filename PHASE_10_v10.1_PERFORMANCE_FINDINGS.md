# Phase 10 v10.1 — Performance Findings

The developer reported "the whole website feels slow and laggy." This document captures what was investigated, what changed, and what's still open.

I'll be honest: this is NOT a complete performance overhaul. It's a focused fix for the easiest, highest-impact hotspots. A real perf pass needs production-like traffic measurement, which the sandbox cannot do.

---

## Changes shipped in v10.1

### 1. Translations cached (every request — high frequency)

**Before:** `HandleInertiaRequests::loadTranslations` opened `lang/en.json` (6.8 KB) + `lang/{locale}.json` (8.4 KB Arabic / 8.2 KB Urdu) on **every single Inertia request**, JSON-decoded both, and array-merged. For a moderately busy page at 50 req/s, that's ~100 disk reads/s and ~750 KB/s of pointless I/O for completely static files.

**After:** `Cache::remember('inertia:translations:v1:' . $locale, now()->addHour(), ...)`. With the file cache driver, this becomes one stat() per request instead of two open()s. With Redis, it's a single memory hit.

**Expected gain per request:** ~3-8ms on cold disk, sub-millisecond on Redis. Multiplied by all Inertia requests, this is real.

### 2. Composite database indexes targeting the reports queries

Five new indexes in `2026_06_15_000001_add_phase10_v101_performance_indexes.php`:

| Index | Targets |
|---|---|
| `orders_created_status_idx` ON orders (created_at, status) | every `adminFinancialSummary` query |
| `order_items_vendor_order_idx` ON order_items (vendor_id, order_id) | `vendorFinancialSummary`, `topVendorsByGross` |
| `order_items_product_idx` ON order_items (product_id) | `topProductsByUnits` GROUP BY |
| `products_status_type_idx` ON products (status, type) | catalog filters + sitemap |
| `products_category_status_idx` ON products (category_id, status) | catalog category filter |
| `product_reviews_prod_status_idx` ON product_reviews (product_id, status) | `approvedReviews` |
| `vpr_created_status_idx` ON vendor_payout_requests (created_at, status) | payout summary |

These match the actual WHERE + JOIN shapes the queries use. The migration is idempotent (re-runs without error).

**Expected gain:** for reports queries on a table with 100k+ orders, the composite indexes turn full-scan + filesort into bounded index range scans. Without measurement against real production data the speedup is unquantifiable but qualitatively significant.

### 3. Mobile layout changes that reduce DOM size

The new mobile menu drawers render the link set ONLY when `mobileOpen === true`. Previously the wide nav rendered ~15 vendor links + dropdown menus on every page in every viewport. The desktop nav is also now wrapped in `flex-wrap` to reduce overflow-driven layout thrash.

---

## What was investigated and rejected

### Reduce shared Inertia props

I looked at `HandleInertiaRequests::share` for large prop payloads. The translations are now cached. `cart_summary` is computed via a closure (already lazy). `auth.user` is also lazy. No standalone "huge prop" was found.

If the dev observes specific pages with > 100 KB Inertia props, that's a per-page issue best handled in the relevant controller — not in shared props.

### Move catalog browsing to a read-replica

Out of scope for v10.1. Worth considering if catalog browsing becomes the bottleneck at scale.

### Image lazy-loading

Product card images already use `loading="lazy"` (verified in `Catalog/Index.tsx`). Vendor file previews in the admin Filament view also use `loading="lazy"`.

### Disabling strict-mode lazy-load checks

Strongly resisted. The v9.5 review-display bug was a strict-mode catch; turning it off in production would hide future bugs. Production already has `Model::shouldBeStrict(false)` (via `! isProduction()` check in AppServiceProvider) — the strict checks only apply in non-prod environments, where the perf cost doesn't matter.

---

## What was NOT investigated (out of scope)

The dev's report mentioned "the whole website feels slow." That's a symptom that could have many causes I can't reproduce in this sandbox:

- **Network latency to MySQL** — if the DB is on a remote host (e.g. RDS) with latency, every query pays a cost. Solution: put PHP-FPM in the same VPC/region as MySQL.
- **PHP-FPM worker count** — too few workers → requests queue. Tune `pm.max_children` for the host's RAM.
- **Opcache disabled or under-allocated** — production must have OPcache enabled with `opcache.memory_consumption=256` minimum and `opcache.validate_timestamps=0` for production-mode revalidation.
- **No CDN** — if static assets (Vite-built JS/CSS, product images) are served from the PHP host, latency is bad. Cloudflare in front + cache-control headers help dramatically.
- **Redis vs file cache** — if the dev was testing on the default file driver, every cache read/write hits disk. Switch to Redis (already supported via `.env` `CACHE_DRIVER=redis`).
- **MySQL config** — default `innodb_buffer_pool_size` is 128 MB on most distros. For any real workload, set it to ~70% of RAM.

These are deployment-time tuning, not code defects. The deployment guide already covers each.

---

## Recommended next steps for the developer

To actually measure performance:

```bash
# 1. Install Laravel Debugbar locally
composer require barryvdh/laravel-debugbar --dev

# 2. Visit each suspicious page; the debugbar shows:
#    - query count
#    - duration per query
#    - memory peak
#    - render time

# 3. If any page shows N+1 queries, the debugbar identifies the offending relation
```

For production observability, use Telescope (`composer require laravel/telescope`) in staging only, or a hosted APM like Sentry / DataDog / New Relic.

If a specific page's query count is still high after the v10.1 indexes are applied, share the debugbar screenshot — I can target the actual hot spot in v10.2.

---

## Honest limit acknowledgement

The sandbox can't run `migrate:fresh --seed` then EXPLAIN the queries. The indexes I added match the query shapes from reading the code, not from MySQL's actual query plan. In a real environment:

```sql
EXPLAIN SELECT ... FROM order_items
  JOIN orders ON orders.id = order_items.order_id
 WHERE order_items.vendor_id = 1
   AND orders.created_at BETWEEN '2026-01-01' AND '2026-06-15';
```

Should show the new `order_items_vendor_order_idx` being used. If MySQL still does a full scan, the index may need adjustment (e.g. swap column order based on cardinality).
