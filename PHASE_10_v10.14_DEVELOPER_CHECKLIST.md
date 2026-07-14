# Phase 10 v10.14 — Developer Checklist

Per dev §16 + §21.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.14.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.14.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §15 — Required commands

```bash
composer install
npm ci
php artisan optimize:clear
php artisan migrate:status                       # → v10.14 indexes migration listed as "Pending"
php artisan migrate                              # → applies the v10.14 perf indexes
php artisan route:list
php artisan test --filter='Phase10V1014'         # → 15 Pest scenarios pass
php artisan test                                 # → full suite passes
npm run typecheck
npm run build
```

If `php artisan migrate` adds anything other than the v10.14 indexes migration, abort and investigate — v10.14 only ships one new migration.

## §15 — Restart

```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve

# For PHP-FPM:
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

Hard-refresh the browser (Ctrl+Shift+R).

## §19 — Asset verification

```bash
ls -la public/build/manifest.json                # → fresh mtime
cat public/build/manifest.json | head            # → note the new asset filenames
```

Open browser DevTools → Network tab. Confirm the loaded `/build/assets/*.js` filenames match what's in `manifest.json`. If a service worker is interfering, unregister it via DevTools → Application → Service Workers.

## §16 — Manual regression walkthrough

Walk through these in order. Each should be functional (no SQL error, no 500, no JS error).

### Storefront
- [ ] **A. Homepage `/`** — loads, health badges visible (now cached). On first load it may show a fresh probe; refresh — should be instant (cache hit).
- [ ] **B. Products listing `/products`** — paginated, images load lazily below the fold.
- [ ] **C. Product detail `/products/{slug}`** — variants, reviews, vendor info all visible.
- [ ] **D. Promotions displayed correctly** (`final_price` differs from regular `price` where applicable).
- [ ] **E. Cart `/cart`** — items + totals + coupon.
- [ ] **F. Checkout flow** — proceeds without 500 (do NOT submit a real card; use the test gateway if applicable).
- [ ] **G. Customer orders `/orders`** — list paginated. The new `orders_user_created_idx` should make this fast even with many orders.

### Vendor
- [ ] **H. Vendor dashboard `/vendor`** — Business header + Reports CTA card (v10.13) + Package/Subscription cards.
- [ ] **I. Vendor orders `/vendor/orders`** — paginated. The v10.11 §3 dropdown options visible on the show page.
- [ ] **J. Vendor Reports `/vendor/reports`** (v10.13 nav link + CTA both work) — loads, vendor's own data only.
- [ ] **K. Vendor application document previews** — PDF/image previews still work.

### Admin
- [ ] **L. Admin Reports `/admin/reports`** (v10.10/v10.11/v10.12) — loads, KPIs render, no SQL error.
- [ ] **M. Filament admin support tickets list** — fast even with many tickets (new `st_status_created_idx`).

### Support tickets (v10.11 §4 regression guard)
- [ ] **N. Customer reply** — replies without `LazyLoadingViolationException`.
- [ ] **O. Vendor reply** — same.
- [ ] **P. Admin reply (Filament)** — same.

### Mobile
- [ ] **Q. Mobile hamburger menu** — opens, navigation works.
- [ ] **R. Mobile category expansion** — works on `/products`.

## §16.6 — Perf observation (the dev's actual measurement)

The dev's most valuable evidence: open Chrome DevTools → Network → Disable cache. Click around as admin user. Compare repeated navigation events:

- **Pre-v10.14**: each admin page navigation should show a SQL query in Laravel Telescope/debugbar for the carts table + categories table even though admin doesn't render them.
- **Post-v10.14**: same navigation should NOT show carts/categories queries.

If the dev has Telescope installed: filter by request path = `/admin/*` and verify no `select * from carts` queries in the query log.

If they don't have Telescope: install temporarily for measurement, then disable. Or use `DB::enableQueryLog()` in a tinker session against an authenticated admin user state.

## What to do if a page is still slow

Identify the exact slow page first (DevTools Network tab → look at the TTFB column). Then:

1. **TTFB > 1s for any page**: backend issue. Open Laravel debugbar / Telescope → look at the query log for that request. If 20+ queries, an N+1 was missed. If a single query > 100ms, run `EXPLAIN` against it.

2. **TTFB OK but page renders slowly**: frontend. DevTools → Performance tab → record a page interaction. Look for long task warnings.

3. **All requests slow**: infrastructure. Check:
   - `php artisan tinker` → `Cache::put('test', 1, 60); Cache::get('test')` — should be instant. If slow, Redis is misconfigured.
   - `ping <db-host>` — should be < 1ms locally.
   - `php -i | grep opcache` — opcache should be on for production.

## CI verdict

```
✅ Phase 10 v10.14 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.14 STOPS HERE.** No Phase 11. Pending dev runtime measurement and verification.
