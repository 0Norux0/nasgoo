# Phase 9 v9.4 — Developer Testing Checklist

v9.4 is a small surgical correction on top of v9.3. The Codex audit produced 24 findings; 8 turned out to be real (3 production, 5 test). The other 16 are documented in `PHASE_9_v9.4_VERIFICATION_MATRIX.md` with evidence.

---

## 0. Apply v9.4

```bash
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php
tar -xzf marketplace-phase-9-v9.4.tar.gz --strip-components=1 --overwrite
composer dump-autoload -o
cat VERSION                   # → Phase 9 v9.4
php artisan marketplace:version
```

---

## 1. Run the full verification

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed             # must complete cleanly on MySQL
php artisan test --filter='Phase9V94'        # 4 new scenarios
php artisan test --filter='Phase9'           # 49 total Phase 9 scenarios
php artisan test                             # full suite
npm ci
npm run typecheck                            # 0 errors
npm run build
```

---

## 2. Manual sanity checks

### Finding #22 — MySQL catalog search

```bash
php artisan serve &
curl -s 'http://localhost:8000/products?q=Demo' | head -20
# Pre-v9.4 on MySQL: 500 error "Unknown operator 'ILIKE'"
# Post-v9.4: 200 with rendered HTML
```

### Finding #25 — multi-vendor partial shipment

1. Place a multi-vendor order (cart with items from vendor1 + vendor2)
2. Pay it. Order status: `paid`. Fulfillment: `unfulfilled`.
3. Log in as `vendor@marketplace.test`. Open `/vendor/orders/{id}`. Click "Mark items shipped".
4. Refresh the order. **Fulfillment should now be `partial`, not still `unfulfilled`.** This is the pre-v9.4 stale-read bug.
5. Log in as `vendor2@marketplace.test`. Mark their items shipped. **Fulfillment should now be `fulfilled`.**

### Finding #20 — no 419 on cart/checkout HTTP tests

Run any cart or checkout HTTP test after `DemoSeederTest`:

```bash
php artisan test tests/Feature/DemoSeederTest.php
php artisan test --filter='Phase9V93RegressionTest::it checkout page shows'
# pre-v9.4: cart/checkout tests returned 419 (CSRF re-enabled because the env was 'local')
# post-v9.4: both green
```

---

## 3. CI verdict

```
## 🎯 Phase 9 v9.4 Verification Result
### ✅ Phase 9 v9.4 PASSES — ready to approve Phase 10
```

Confirm green:

- Phase 7 sub-checks (14)
- Phase 8 sub-checks (20)
- Phase 9 v9.0/v9.1/v9.3 sub-checks (14)
- Phase 9 v9.4 sub-checks (5):
  - Catalog search uses portable LOWER() LIKE (no ILIKE)
  - refreshFulfillment force-reloads items
  - All seeders use null-safe `?->command`
  - DemoSeeder uses scoped config flag (not env-mutation)
  - Phase 9 v9.4 Pest scenarios pass

---

## 4. STOP — do not start Phase 10

After CI is green AND the v22 (preserved v9.3 flows) checklist still passes:

- Coupon persists cart → checkout → order ✓
- Order summary shows coupon ✓
- Vendor earnings + discount allocation reconcile ✓
- Customer can review delivered products ✓
- Admin support ticket page loads without lazy-load ✓
