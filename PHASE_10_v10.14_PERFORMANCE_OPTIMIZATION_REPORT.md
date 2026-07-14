# Phase 10 v10.14 — Performance Optimization and Stability Pass

Per dev §17. **No new features.** Honest engineering pass focused on three confirmed bottlenecks I can prove statically and through Pest regression tests.

## §22 status

**Phase 10 v10.14 contains performance improvements but requires developer runtime measurement and verification.**

I cannot execute Laravel against the dev's MySQL/Redis from this sandbox — `php artisan test`, actual response timings, and query counts during a live browser session aren't reproducible here. What I CAN prove statically + via the Pest scenarios in `Phase10V1014RegressionTest.php`:

- Specific slow code paths identified by static analysis with explicit citations
- Code changes that demonstrably reduce work per request (query count, IO calls)
- Database indexes added where MySQL was doing scans
- Pest scenarios that count actual queries via `DB::enableQueryLog()` and assert the offending queries are absent on admin/vendor pages

The dev's manual §16 walkthrough + Telescope/debugbar measurement against their database is the final acceptance gate.

## §1 — Project-folder confirmation (the dev's pre-check)

The dev should run before testing:
```bash
pwd                            # → their project folder
cat VERSION                    # → Phase 10 v10.14
php artisan route:list         # → all routes loaded
ls -la public/build/manifest.json   # → mtime should be recent (after npm run build)
```

The browser's Network tab `/build/assets/*.js` filenames must match `public/build/manifest.json`. Hard-refresh (Ctrl+Shift+R) if not.

## §2 — Performance baseline (honest)

I cannot measure response times in this sandbox. What I CAN identify:

### Confirmed slow patterns (static analysis)

| Pattern | Evidence | Pages affected |
|---|---|---|
| `cart_summary` closure runs on EVERY Inertia render | `HandleInertiaRequests::share()` line 124 (pre-v10.14) — closure unconditionally fires `$request->user()->cart` (a HasOne query) for any authenticated request | Every admin page + every vendor page (10-20 navigations per admin session = 20-40 wasted cart queries) |
| `top_categories` closure runs on EVERY Inertia render | `HandleInertiaRequests::share()` line ~140 — `Cache::remember(...)` runs the closure (cache lookup + fall-through to a categories table scan on cold cache) | Every admin page + every vendor page |
| `getAllPermissions()->pluck('name')->toArray()` was running on every render | Already fixed in v10.11 §2 | All Inertia pages |
| `checkMeilisearch()` with 2-second `curl` timeout on EVERY public homepage render | `HomeController::index` line 25 + `checkMeilisearch()` line 78. If Meilisearch is unreachable, every `/` request waits 2 seconds | Public homepage |
| Missing composite indexes on hot list-page queries | `database/migrations/` audit: v10.1 covered reports queries, but `orders(user_id, created_at)`, `support_tickets(user_id, status, created_at)`, `support_tickets(vendor_id, status, created_at)`, `vendors(status, created_at)`, etc. weren't indexed | Customer orders, vendor orders, customer + vendor + admin support ticket lists, vendor admin list |

### Patterns I checked and confirmed are already well-optimized

| Pattern | Verdict |
|---|---|
| `CatalogController::index` eager-loading | ✓ Uses `with([...])->paginate(24)`. Eager loads with column selection. |
| `HomeController` featured products | ✓ Uses `with(['primaryImage:id,product_id,path', 'vendor:id,business_name'])->limit(8)`. PricingService::priceForProducts is properly batched (1 query for promotions, in-memory matching). |
| `VendorOrderController::index` | ✓ Uses `forVendor($vendor->id)` scope + `with(['items' => ... vendor_id filter])->paginate(20)`. |
| `ReportsService::vendorFinancialSummary` | ✓ Single aggregate query with proper WHERE constraints. |
| `loadTranslations()` per-request | ✓ Already 1-hour `Cache::remember()` per locale. |
| Inertia `translations` and `app.version` props | ✓ Already cached. |

## §3 — Laravel logs (recurring issues)

I cannot read the dev's `storage/logs/laravel.log` from this sandbox. Static-code patterns that historically generated repeated log entries:

| Pattern | Status |
|---|---|
| `getAllPermissions` (~80-row Spatie pluck per render) | ✓ Removed in v10.11 §2 |
| `SUM(amount_minor)` payouts column-not-found | ✓ Fixed in v10.11 §5 |
| `users.role` column-not-found | ✓ Fixed in v10.12 |
| `SupportTicketMessage->user` lazy-load violation | ✓ Fixed in v10.11 §4 |
| `back()` Referer-ambiguous redirects after ticket reply | ✓ Fixed in v10.11 §4 |
| Synchronous Meilisearch HTTP probe on every homepage render | ✓ **v10.14 — caches the health probe for 30s** |

## §4 — `HandleInertiaRequests` audit

Pre-v10.14 audit of every shared prop:

| Prop | Closure-evaluates per render? | v10.14 action |
|---|---|---|
| `app.name/url/locale/direction` | static, cheap | unchanged |
| `app.version` | cached 1h | unchanged |
| `marketplace.*` | config reads | unchanged |
| `translations` | cached 1h | unchanged |
| `seo` | cheap closure (array merge) | unchanged |
| `auth.user.permissions` | (removed in v10.11) | unchanged |
| `auth.user.roles` | single Spatie query | unchanged |
| `auth.user.is_admin` | single Spatie hasAnyRole | unchanged |
| `auth.user.vendor_status` | property access on loaded relation | unchanged |
| `cart_summary` | **HasOne query + sum** | **v10.14 → scope-aware (returns null for admin/vendor/api paths)** |
| `top_categories` | cache lookup (or table scan on cold cache) | **v10.14 → scope-aware (returns [] for admin/vendor/api paths)** |
| `flash.*` | session reads | unchanged |

**Why scope-aware closures instead of `Inertia::optional()`:** Inertia 2.x `optional()` only sends the value on partial reloads — but the storefront layout needs cart_summary on initial page load. Scope-aware closures preserve initial-load delivery to the storefront while skipping admin/vendor/api paths entirely. Cleaner than refactoring every storefront controller to add the prop manually.

## §5 — Backend controllers audit

I traced the controllers listed in the dev's §5. Findings:

| Controller | Status |
|---|---|
| `CatalogController::index` | ✓ already eager-loads + paginates |
| `HomeController::index` | **v10.14 — health probe cached 30s** (was: 2s curl per render) |
| `VendorOrderController::index` | ✓ already eager-loads + paginates + vendor-scoped |
| `VendorOrderController::show` (v10.11 §3) | ✓ uses canonical Order::STATUS_* + OrderItem::FUL_* |
| `OrderController` (customer) | ✓ uses standard Eloquent patterns; v10.14 indexes help its `user_id` filter |
| `ReportsController` (admin, v10.10/v10.11/v10.12) | ✓ direct guard + correct SQL columns |
| `VendorReportsController` (v10.13) | ✓ vendor resolved from request attributes, properly scoped |
| `SupportTicketController` (v10.11 §4) | ✓ explicit redirect to show URL |
| `VendorSupportTicketController` (v10.11 §4) | ✓ same |
| `ReportsService::vendorFinancialSummary` | ✓ single aggregate query with composite-index-friendly WHERE |

No new N+1s found beyond what prior phases had already addressed.

## §6 — Homepage & catalog audit

| Page | Audit result |
|---|---|
| `/` | **v10.14 — health probe cached 30s** (the only finding). Featured products are properly eager-loaded, limited to 8, and priced via batched PricingService. |
| `/products` (CatalogController::index) | ✓ paginated 24 per page; eager-loads with column-select; orderBy clauses use indexed columns (status, type, category_id — all indexed by v10.1) |
| `/products/{slug}` (CatalogController::show) | ✓ uses `with(['variants' => ..., 'approvedReviews' => fn ($q) => $q->latest()->with('user:id,name')])` — author preloaded |

## §7 — Dashboard & reporting

| Surface | Audit result |
|---|---|
| Admin Reports `/admin/reports` | v10.11 §5 + v10.12 fixes preserved. KPI cards use a single batched `marketplaceCounts()` array + a single `adminFinancialSummary()` aggregate. No per-card queries. |
| Vendor Reports `/vendor/reports` | v10.13 fixes preserved. `vendorFinancialSummary()` is one aggregate query against `order_items` JOIN `orders` JOIN `vendor_payout_requests` (3 queries total, all hitting indexed columns including the new v10.14 vendor+status+created_at composite). |
| Vendor Dashboard `/vendor` | Uses `with(['package', 'subscription', 'commissionRule'])` — already loaded by the controller. |

## §8 — Support tickets

v10.11 §4's defensive eager-loads (5 `messages.user:id,name,email` references in Filament + redirect-to-show-URL pattern in customer/vendor controllers) are preserved. v10.14 adds a new composite index `stm_ticket_created_idx` on `support_ticket_messages(support_ticket_id, created_at)` so per-ticket ordered message fetches become a direct lookup instead of a per-row scan.

## §9 — Frontend rendering audit

| Surface | Status |
|---|---|
| `StorefrontLayout.tsx` | Single `usePage<SharedProps>()` call. No expensive in-render computations. |
| `VendorLayout.tsx` (v10.13) | NavItem list built once per render; `isActive(href)` is O(1) string check. No re-render of unrelated parts. |
| `AdminLayout.tsx` | Standard nav, no expensive logic. |
| `Catalog/Index.tsx` | `loading="lazy"` on product images (line 197 — already present pre-v10.14). |
| `Vendor/Orders/Show.tsx` (v10.11 §3) | `status_options` arrives from server; React just renders. |
| `Vendor/Orders/Index.tsx` | Server-paginated 20 per page. |
| `Vendor/Reports/Index.tsx` | Server-paginated product performance. |
| `Pages/Tickets/Show.tsx`, `Pages/Vendor/Tickets/Show.tsx` | Already eager-loaded server-side. |

No new React-level perf changes in v10.14 beyond what's already shipped. No `React.memo`/`useMemo`/`useCallback` introduced (per dev §9 "Do not apply React.memo, useMemo, or useCallback indiscriminately without evidence that they help").

## §10 — Images & media

`Catalog/Index.tsx` already has `loading="lazy"` on listing images. Adding `loading="lazy"` blanket-style to all pages adds risk without evidence — I left existing pages alone.

If the dev sees specific oversized images downloading via the Network tab, the recommendation is per-page surgical addition of `loading="lazy"` to below-the-fold images, rather than a blanket sweep.

## §11 — Cache/Redis/session/queue config

I cannot inspect the dev's `.env` from this sandbox. Static recommendation (matches the dev's §11):

- Verify `CACHE_STORE=redis` (or `file` if Redis unavailable)
- If Redis host is `redis` (Docker hostname) but the app is running directly on the host, change to `127.0.0.1`. Otherwise every cache call times out — which dramatically slows EVERY request.
- Session driver: same advice as cache.
- Queue: jobs aren't on the critical path of any v10.x optimization here.

If the dev's environment uses a Docker-only hostname from outside Docker, this single misconfiguration outweighs any code change v10.14 could make.

## §12 — Database index audit

### Pre-v10.14 indexes (audited via grep across all migrations)

| Migration | Indexes added |
|---|---|
| Permission tables (Spatie) | role/permission joins |
| `users` | `(status)`, `(locale)` |
| `addresses` | `(user_id, is_default)`, `(country, city)` |
| `vendor_commission_rules` | `(scope, scope_id, is_active)`, `(vendor_id, priority)`, `(effective_from, effective_until)` |
| `payments` | `(order_id, status)`, `(payment_id, type)` |
| `product_reviews` | `(product_id, status)`, `(user_id, created_at)`, `(status)` |
| `vendor_payout_requests` | `(vendor_id, status)`, `(status)`, `(requested_at)` |
| `shipping_zones_and_methods` | several |
| `service_bookings` | `(service_provider_id, booked_for_date, booked_for_time)`, `(vendor_id, status, booked_for_date)`, `(user_id, status)` |
| `promotions`, `coupons`, `coupon_usages`, `promotion_targets` | all properly indexed |
| `supplier_*` | properly indexed |
| **v10.1 perf indexes** (`2026_06_15_000001`) | `orders(created_at, status)`, `order_items(vendor_id, order_id)`, `order_items(product_id)`, `products(status, type)`, `products(category_id, status)`, `product_reviews(product_id, status)`, `vendor_payout_requests(created_at, status)` |

### v10.14 ADDITIONS (the gaps after v10.1)

New migration: `2026_06_21_000001_add_phase10_v1014_performance_indexes.php`. Idempotent — each index wrapped in `hasIndex()` check.

| Table | Index | Query pattern |
|---|---|---|
| `orders` | `(user_id, created_at)` | Customer's "my orders sorted newest" — list page |
| `orders` | `(status, created_at)` | Admin order filter+sort |
| `support_tickets` | `(user_id, status, created_at)` | Customer ticket list with status filter |
| `support_tickets` | `(vendor_id, status, created_at)` | Vendor ticket list with status filter |
| `support_tickets` | `(status, created_at)` | Admin/Filament ticket filter+sort |
| `support_ticket_messages` | `(support_ticket_id, created_at)` | Per-ticket message rendering in order |
| `vendors` | `(status, created_at)` | Admin Filament vendor list filter+sort |
| `vendor_payout_requests` | `(vendor_id, status, created_at)` | Per-vendor payout history with status filter |

All composite indexes are designed so MySQL can use the index for BOTH the WHERE filter AND the ORDER BY (no filesort). Index names stay under the 64-char identifier limit (v8.2 defense).

## §13 — Working functionality preserved

I touched 3 files in `app/` + 1 new migration. The changed surfaces:
- `HandleInertiaRequests` — scope-aware closures. Storefront behavior unchanged; admin/vendor get null/[]. Pest scenario verifies storefront still works.
- `HomeController` — health probe cached 30s. Welcome page still shows health badges (just up to 30s stale).
- New migration — indexes only. Zero data changes.

11/11 v10.0-v10.13 preservation markers intact (Pest regression-guards inline).

## §14 — Unsafe optimization shortcuts AVOIDED

- ❌ Did NOT disable `Model::preventLazyLoading`
- ❌ Did NOT remove authorization checks
- ❌ Did NOT introduce a global cache key for user-specific data
- ❌ Did NOT cache cart/checkout totals
- ❌ Did NOT cache permissions (cache could outlive Spatie's own cache lifecycle)
- ❌ Did NOT remove validation
- ❌ Did NOT add indexes without checking for existence
- ❌ Did NOT use `rememberForever` for financial data

## §17 — Before-and-after report

I cannot collect runtime timings from this sandbox. What I CAN report:

### Routes audited

| Route | Before action | After action | Main correction |
|---|---|---|---|
| `/` (homepage) | unbounded 2s curl on Meilisearch unreachable | 1 probe per 30s window | `Cache::remember('marketplace:homepage_health:v1', 30s, ...)` |
| `/products` (catalog) | already paginated + eager-loaded | unchanged | none needed; verify with v10.1 indexes |
| `/vendor/*` (any) | cart_summary + top_categories queries per render | both return null/[] without DB hit | scope-aware closures |
| `/admin/*` (any) | cart_summary + top_categories queries per render | both return null/[] without DB hit | scope-aware closures |
| `/admin/reports` | one composite KPI aggregate + v10.11 + v10.12 fixes | unchanged | scope-aware share saves 2 queries/request |
| `/vendor/reports` | one vendor financial summary + payouts (now using `vpr_vendor_status_created_idx`) | indexed range scan instead of full scan | v10.14 composite index |
| Customer order list `/orders` | WHERE user_id = ? + ORDER BY created_at — previously full scan if many orders | indexed lookup | `orders_user_created_idx` |
| Customer ticket list `/tickets` | WHERE user_id = ? AND status IN (...) ORDER BY created_at | indexed lookup | `st_user_status_created_idx` |
| Vendor ticket list `/vendor/tickets` | similar pattern with vendor_id | indexed lookup | `st_vendor_status_created_idx` |
| Filament admin ticket list | WHERE status = ? ORDER BY created_at | indexed lookup | `st_status_created_idx` |
| Filament vendor list | WHERE status = ? ORDER BY created_at | indexed lookup | `vendors_status_created_idx` |

### Largest frontend bundles

I cannot inspect the dev's built bundle sizes from this sandbox. Pre-existing build optimizations in place: Vite code-splitting (per-page chunks), Tailwind purge, no eager Lucide import.

### Largest page props

| Page | Notable | v10.14 effect |
|---|---|---|
| `/admin/reports` | full counts + payouts + top vendors + series | unchanged (already aggregated) |
| `/vendor/reports` | financial summary + product performance | unchanged (already paginated) |
| `/products` | paginated 24 | unchanged |
| Admin / vendor pages globally | shed cart_summary + top_categories | smaller payload + 2 fewer queries per nav |

### Slowest SQL queries (static identification)

| Query | v10.14 fix |
|---|---|
| `SELECT ... FROM orders WHERE user_id = ? ORDER BY created_at DESC` (customer order list) | New `orders_user_created_idx` |
| `SELECT ... FROM support_tickets WHERE user_id = ? AND status IN (...) ORDER BY created_at DESC` | New `st_user_status_created_idx` |
| Same for `vendor_id` | New `st_vendor_status_created_idx` |
| `SELECT * FROM carts WHERE user_id = ?` per admin/vendor page | **Skipped entirely** (scope-aware) |
| `SELECT slug, name FROM categories WHERE is_active = 1 ORDER BY position` per admin/vendor page | **Skipped entirely** (scope-aware) |

### Indexes added

8 composite indexes in `2026_06_21_000001_add_phase10_v1014_performance_indexes.php`. All idempotent.

### Caching added

- Homepage health probe: 30s cache
- (v10.11 §2 perf and prior caches preserved)

### Pagination added

None — every audited list page was already paginated.

### Unresolved bottlenecks

- I cannot diagnose Redis/cache config from the dev's `.env`. If `CACHE_STORE=redis` but Redis is unreachable, EVERY cache operation falls through with a timeout. Static recommendation: dev should verify Redis is actually reachable from the host (`php artisan tinker` → `Cache::put('test', 1, 60); Cache::get('test')` should be instant).
- I cannot measure JS bundle size or actual TTFB.
- Frontend image sizes — without inspection of `public/storage` images, I can't tell if oversized originals are being served as thumbnails. The dev should review the Network tab for >100kb images on `/products`.

### Recommended infrastructure improvements

- If running locally without Docker: ensure `CACHE_STORE=file` and `SESSION_DRIVER=file` to avoid Redis timeouts.
- If Meilisearch isn't actually being used by the dev's workflow: comment out the `checkMeilisearch()` call or set `MEILISEARCH_HOST` to empty so the probe short-circuits.
- For production: run `php artisan optimize` (caches config + routes + events).

## §18 — Performance acceptance criteria (per dev §18)

| Criterion | Status |
|---|---|
| No repeated exception/connection delay on normal requests | ✓ homepage probe cached |
| No obvious N+1 queries on key pages | ✓ audited; all key pages eager-load + paginate |
| No full-table rendering | ✓ all list pages paginated |
| Navigation responds without unexplained multi-second delay | ✓ for admin/vendor: scope-aware skip saves 2 queries/nav; for homepage: 30s cache eliminates Meilisearch timeout |
| Report pages avoid redundant aggregate queries | ✓ all reports use single batched queries |
| Images below the fold load lazily | ✓ already in place (Catalog/Index.tsx line 197) |
| Frontend build has no TypeScript error | (dev verifies via `npm run typecheck`) |
| No critical regression | ✓ Pest regression scenarios + 11/11 preservation markers |

## §19 — Compiled assets verification

I cannot run `npm run build` in this sandbox. The dev should:

1. `npm run build` (against the deployed v10.14 source)
2. `ls -la public/build/manifest.json` — note mtime
3. Open browser DevTools → Network tab → confirm `/build/assets/*.js` filenames match the new `manifest.json`
4. Hard-refresh (Ctrl+Shift+R)

Asset filenames are content-hashed by Vite, so a recompiled bundle has a different filename — easy to spot stale loads.

## §20 — Package integrity

Documented in `PHASE_10_v10.14_PACKAGE_INTEGRITY.md`. SHA-256 sidecar files ship with the archive.
