# Phase 10 v10.8 — Developer Checklist

The dev's §13 manual walk-through, focused.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.8.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.8.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

`scripts/deploy.sh` runs `composer install`, `npm ci`, `npm run build`, `php artisan optimize:clear`, `php artisan config:clear`, full cache flush.

**v10.8 adds ONE NEW migration**:

```bash
php artisan migrate
# expected: "2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns ... DONE"
```

The migration adds (additive, defaulted, safe):
- `orders.promotion_discount_minor` UNSIGNED INT DEFAULT 0
- `order_items.promotion_id` UNSIGNED BIGINT NULLABLE + index
- `order_items.promotion_name` STRING NULLABLE
- `order_items.promotion_discount_minor` UNSIGNED INT DEFAULT 0
- `order_items.original_unit_price_minor` UNSIGNED INT NULLABLE (null when no promotion applied)

## Required confirmations (§17 final clause)

The package may be described as fixed only after ALL of these are manually verified.

### ☐ Confirmation A — promotion visible on /products

1. Navigate to `/deals` — confirm "Summer Flash Sale — 20% off all products" still renders.
2. Navigate to `/products`.
3. Every product card should show:
   - **Final price** (rose color) and **strikethrough original**
   - **"20% OFF" badge** (rose pill)

### ☐ Confirmation B — promotion on product detail

1. Click any product card → product detail page.
2. The detail's price block should match the listing price exactly.
3. Strikethrough original, large final price, "20% OFF" badge — all visible.

### ☐ Confirmation C — promotion in cart

1. Add a 110 KWD product to cart (or whichever eligible product) → go to `/cart`.
2. Cart line should show:
   - Per-line final total in rose
   - Strikethrough line original
   - Promotion badge on the line (testid `cart-line-promoted-{id}`)
3. Cart summary should show, in order:
   - `Subtotal: 110.00 KWD`
   - `Promotion discount: −22.00 KWD` (rose)
   - `Subtotal after promotion: 88.00 KWD` (small gray)
   - `Shipping + tax: calculated at checkout`
   - `Subtotal after discount: 88.00 KWD` (bold)

### ☐ Confirmation D — stacking with SAVE10

1. With the 110 KWD product in cart, apply coupon `SAVE10`.
2. Cart summary should now show:
   - `Subtotal: 110.00 KWD`
   - `Promotion discount: −22.00 KWD`
   - `Subtotal after promotion: 88.00 KWD`
   - `Coupon (SAVE10): −8.80 KWD` (green) ← **NOT −11.00** (which would be 10% of raw 110)
   - `Subtotal after discount: 79.20 KWD`

If you see `Coupon: −11.00 KWD`, the v10.8 stacking is NOT active — the deployment is still on v10.7 logic.

### ☐ Confirmation E — checkout shows same numbers

1. Click "Checkout" → checkout summary.
2. Should show the EXACT same breakdown as the cart (`Subtotal 110`, `Promotion 22`, `Coupon 8.80`, `Payable 79.20`).

### ☐ Confirmation F — order persisted with promotion snapshot

1. Place the order.
2. Open `mysql` (or your DB client):

```sql
SELECT subtotal_minor, promotion_discount_minor, coupon_discount_minor,
       shipping_minor, tax_minor, total_minor
FROM orders ORDER BY id DESC LIMIT 1;
```

Expected:
- `subtotal_minor` = 11000 (gross — unchanged semantic)
- `promotion_discount_minor` = 2200 (NEW v10.8 column)
- `coupon_discount_minor` = 880
- `total_minor` = `subtotal − promotion − coupon + shipping + tax` = `11000 − 2200 − 880 + ship + tax`

```sql
SELECT promotion_id, promotion_name, promotion_discount_minor,
       original_unit_price_minor, unit_price_minor, line_total_minor,
       coupon_allocation_minor, commission_amount_minor, vendor_earning_minor
FROM order_items WHERE order_id = (SELECT id FROM orders ORDER BY id DESC LIMIT 1);
```

Expected on the 110 KWD line:
- `promotion_id` = (the Summer Flash Sale ID)
- `promotion_name` = "Summer Flash Sale"
- `promotion_discount_minor` = 2200
- `original_unit_price_minor` = 11000 (the pre-promotion unit)
- `unit_price_minor` = 8800 (the POST-promotion unit — what the customer actually paid per unit)
- `line_total_minor` = 8800
- `coupon_allocation_minor` = 880
- `commission_amount_minor + vendor_earning_minor` = `8800 − 880 = 7920` (reconciles)

### ☐ Confirmation G — order summaries reconcile across views

Open the same order under:
1. Customer order detail (`/orders/{number}`)
2. Vendor order detail (`/vendor/orders/{id}`)
3. Admin order detail (Filament `/admin/orders/{id}`)

All three views must show identical mathematics:
- Subtotal: 110.00 KWD
- Promotion: 22.00 KWD
- Coupon: 8.80 KWD
- Total: (subtotal − promotion − coupon + ship + tax)
- Vendor earnings + platform commission sum to the post-promotion + post-coupon line total

## Targeted Pest run

```bash
php artisan test --filter='Phase10V108'
```

All 20 scenarios should pass. Specifically scenario 7 asserts the exact 110 → 22 → 88 → 8.80 → 79.20 stacking from Confirmation D above.

## If something fails

If any confirmation A-G fails, the deployed source is not actually v10.8. Verify:

```bash
cat VERSION
# Expected: Phase 10 v10.8

php artisan marketplace:verify-fixes
php artisan marketplace:fingerprint

# Inspect the wiring:
grep -c "PricingService" app/Http/Controllers/CartController.php       # Expected: 3
grep -c "PricingService" app/Domain/Order/CheckoutService.php          # Expected: 2
grep -c "promotion_discount_minor" app/Models/Order.php                # Expected: 1
```

If the cart line shows the right promotion math but the database doesn't have `promotion_discount_minor`, the migration hasn't run. Run `php artisan migrate` and verify with `\d order_items` (Postgres) or `DESCRIBE order_items;` (MySQL).

## CI verdict

After CI runs against this archive, the final job should output:

```
✅ Phase 10 v10.8 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.8 STOPS HERE.** No Phase 11. Pending your verification of confirmations A-G.
