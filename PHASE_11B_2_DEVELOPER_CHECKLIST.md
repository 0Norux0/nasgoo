# Phase 11B.2 — Developer Checklist

Concise verification steps after extracting `marketplace-phase-11B-2-recommendations.tar.gz`.

## 1. Confirm extracted package matches workspace

```bash
cat VERSION   # → Phase 11B.2
sha256sum -c marketplace-phase-11B-2-recommendations.tar.gz.sha256
```

## 2. Apply migrations

```bash
php artisan optimize:clear
php artisan migrate:status | grep 2026_07_01   # 4 v11B.2 pending
php artisan migrate                              # apply all 4 additive tables
```

Expected: `product_pair_stats`, `product_recommendations`, `admin_product_relationships`, `recommendation_events` — all created.

## 3. Initial aggregation

```bash
php artisan recommendations:generate --truncate
```

Confirm output: "Processing N qualifying orders…" then "Aggregation complete — writing X pair stat rows." If your dev DB has no completed orders yet, output reads "Processing 0 qualifying orders" — that's correct.

## 4. Verify routes registered

```bash
php artisan route:list | grep -i recommendation
# → POST /recommendations/events       throttle:60,1

php artisan route:list | grep cart/items/batch
# → POST /cart/items/batch
```

## 5. Verify scheduler

```bash
php artisan schedule:list | grep recommendations
# → recommendations:generate --since=2    Daily at 03:30
```

## 6. Pest tests

```bash
php artisan test --filter=Phase11B2     # 50 scenarios
php artisan test                          # 592 total
```

## 7. Frontend build

```bash
npm ci
npm run typecheck   # zero errors
npm run build       # zero warnings on recommendation components
```

## 8. Browser verification (manual)

Login as customer → open any product detail page:
- [ ] **Frequently Bought Together** section visible when source product has co-occurrence
- [ ] **Similar Products** grid visible (always visible if eligible candidates exist)
- [ ] **Customers Also Bought** grid visible when ≥3 distinct customers' co-purchase
- [ ] Click an item → navigates to product page; analytics event POSTed to /recommendations/events (Network tab)
- [ ] Toggle checkboxes in FBT → combined total updates
- [ ] Click "Add Selected to Cart" → success flash + items in cart

## 9. Localization verification

- [ ] Switch to Arabic (العربية) → all 3 section headings render in MSA
- [ ] Recommended product titles display Arabic if translated; English fallback otherwise
- [ ] Layout is RTL (text-right alignment / logical CSS works)

## 10. Admin Filament verification

Login as admin → navigate to "Recommendations" group:
- [ ] **Product relationships** page lists 0 rows initially
- [ ] Create a "Pinned" relationship: product A → product B → save
- [ ] Reload product A's customer-facing page → product B appears first in Similar
- [ ] **Analytics** page renders aggregated event counts (0s if no events yet)

## 11. Privacy verification

- [ ] Customers Also Bought returns no items when below privacy threshold (default 3 distinct customers)
- [ ] Recommended product payload JSON contains NO email / user_id / order_id
- [ ] `recommendation_events` rows have 64-char hex `session_token` (SHA-256), never raw session ID

## 12. Regression smoke

- [ ] Customer login works
- [ ] Cart add works (single + batch)
- [ ] Checkout works
- [ ] Search works (desktop + mobile drawer + Products page)
- [ ] Admin reports render
- [ ] Vendor reports render
- [ ] Translation audit (`php artisan translations:audit ar`) still reports product_translations workflow

## 13. Feature flags

Confirm each flag turns its section off without breaking the page:

```bash
echo 'RECOMMENDATIONS_SIMILAR_ENABLED=false' >> .env
php artisan optimize:clear
# Open product page — Similar grid is gone, page still renders
echo 'RECOMMENDATIONS_FBT_ENABLED=false'      >> .env
echo 'RECOMMENDATIONS_ALSO_BOUGHT_ENABLED=false' >> .env
php artisan optimize:clear
# Page renders with NO recommendation sections; no errors
```

Remove the env flags or set to `true` when done.

## 14. Performance smoke

Open Chrome DevTools Network panel → reload a product page that has all 3 sections populated:
- [ ] Total recommendations-related XHR: 0 (everything in initial Inertia render)
- [ ] Initial Inertia payload size: < 100 KB for the recommendations block
- [ ] No console errors; no React hydration warnings

## Done

All boxes checked → Phase 11B.2 verified.
