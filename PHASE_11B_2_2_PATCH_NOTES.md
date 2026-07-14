# Phase 11B.2 v11B.2.2 — Patch Notes

## Summary

Launch-blocking financial-display defect on the checkout page repaired. Customers saw the **un-discounted original price** at checkout (and on the Place Order button) for any active promotion, even though the actual server-side order creation was correctly applying the discount. Two-file surgical fix backed by 45 Pest scenarios covering the entire pricing lifecycle.

## The defect (Summer Flash Sale example)

- Product: KWD 100.000
- Active Summer Flash Sale: 20% off
- Cart page: showed KWD 80.000 ✓
- **Checkout page: showed KWD 100.000 and "Place order — KWD 100.000"** ✗
- Order created in DB: KWD 80.000 ✓ (server was correct all along)
- Payment gateway charged: KWD 80.000 ✓

The customer was MISLED into believing they'd pay the full price.

## Root cause

**Defect A** — `CheckoutController::index` emitted only `unit_price` / `line_total` per line (the pre-promotion `cart_items.unit_price_minor` snapshot from add-to-cart time), missing the `unit_price_final` / `line_total_final` / `line_promotion` / `promotion` fields that the cart page already emitted from the same `PricingService::priceForCart` breakdown.

**Defect B** — `Checkout/Show.tsx` line 152 recomputed the total client-side as `totalMinor = cart.subtotal_minor + shippingMinor - couponMinor` — using the PRE-promotion subtotal and subtracting only the coupon, completely ignoring `cart.promotion.discount_minor`.

## Files changed (5)

**MODIFIED (3)**:
- `app/Http/Controllers/CheckoutController.php` — index() now emits per-line `unit_price_final`, `line_total_final`, `line_promotion`, `promotion` (mirrors CartController exactly; one source of truth via PricingService)
- `resources/js/Pages/Checkout/Show.tsx` — totalMinor = `cart.payable_minor + shippingMinor` (server-authoritative); per-line render uses `line_total_final` with `line_promotion` label; summary renders `Promotion savings` line above coupon
- `app/Domain/Pricing/PricingService.php` — added `priceProductWithQuantity($product, $qty, $customer, $context)` matching dev §3 canonical API signature, returning dev's full contract including `calculation_at` + `pricing_version` hash

**NEW (2)**:
- `tests/Feature/Phase11B22UnifiedPricingTest.php` — 45 Pest scenarios across 10 groups (§23 regression, §24 promotion types, §25 coupons + stacking, §26 checkout + orders + multi-vendor + variants, §27 money safety, §28 reconciliation, §29 atomicity, §30 perf, §31 security, §REG regression)
- `PHASE_11B_2_2_UNIFIED_PROMOTION_CHECKOUT_PRICING_REPAIR.md` — full report

**MODIFIED (CI + version)**:
- `.github/workflows/ci.yml` — +8 v11B.2.2 sub-checks
- `VERSION` — `Phase 11B.2 v11B.2.1` → `Phase 11B.2 v11B.2.2`

## What's NOT changed (already correct from prior phases)

- `PricingService::priceForCart` / `::priceForProduct` (Phase 10 v10.8 canonical engine)
- `CheckoutService::place` (already snapshots all promotion + coupon fields per item)
- `PaymentService::initialize` (already uses `order->total_minor`)
- Snapshot migrations (Phase 9 v9.3 + Phase 10 v10.8 — fully comprehensive)
- `CartController::index` (was already emitting all the right per-line fields)

The repair is **purely display-layer**. No data model changes. No new migrations. No service rewrites.

## Required-result mapping (per dev directive)

| Required result | Status |
|---|---|
| Summer Flash Sale discounted price remains identical from product page through cart, checkout, payment, order, and reports | ✅ §23.1-5 + §28.1 (cross-surface reconciliation) |
| All discounts recalculated server-side | ✅ §31.1, §31.2 (tampered cart values ignored; server re-derives from product price) |
| Future promotions and coupons use the same canonical pricing engine | ✅ §3 — PricingService is the only engine; CheckoutController/CartController/CheckoutService all call it |
| Promotion/coupon stacking is explicit | ✅ §7 documented (best automatic only); §25.3 verifies coupon applies on subtotal_after_promotion |
| Multi-vendor allocations reconcile | ✅ §26.7 (per-vendor promotion isolated); §28.2-3 (sums reconcile) |
| Historical orders retain immutable discount snapshots | ✅ §26.4 (promotion deletion); §26.5 (product price change) |
| Refunds and payouts use the amount actually paid | ✅ §26.3 (payment.amount_minor == order.total_minor); existing PaymentService::refund preserved |
| No client-submitted price trusted | ✅ §31.1-2 |
| No marketplace regression | ✅ §REG.1-4 + all preservation markers intact |

## Counts

| Metric | v11B.2.1 | v11B.2.2 | Δ |
|---|---|---|---|
| CI sub-checks | 154 | **162** | +8 |
| Pest scenarios | 630 | **675** | +45 |
| Unique Pest helpers | 143 | **151** | +8 (p11b22_*) |
| Migrations added | (cumulative) | 0 | — |
| Services added | 0 | — |
| Lines added | ~280 (display layer + tests + docs) | — |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate:status                       # no new migrations expected
php artisan route:list | grep -i checkout
php artisan route:list | grep -i promotion
php artisan route:list | grep -i coupon
php artisan test --filter=Phase11B22             # 45 v11B.2.2 scenarios
php artisan test --filter=Promotion              # legacy promotion tests
php artisan test --filter=Coupon                 # legacy coupon tests
php artisan test --filter=Checkout               # legacy checkout tests
php artisan test --filter=Pricing                # legacy pricing tests
php artisan test                                  # 675 total
npm ci && npm run typecheck && npm run build
```

## Rollback

3-tier per dev §35 item 14:

**Tier 1** — Revert display layer only (re-introduces the Pre-v11B.2.2 misleading display; financial integrity intact):
```bash
tar -xzf marketplace-phase-11B-2-1-recommendation-repair.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Http/Controllers/CheckoutController.php \
    marketplace/resources/js/Pages/Checkout/Show.tsx
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build
```
**Warning**: this reintroduces the launch-blocker. Only use if v11B.2.2 introduced a different display defect.

**Tier 2** — Partial revert (keep PricingService::priceProductWithQuantity; revert display only):
Same as Tier 1.

**Tier 3** — Full revert to v11B.2.1:
```bash
tar -xzf marketplace-phase-11B-2-1-recommendation-repair.tar.gz --strip-components=1 --overwrite
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build
cat VERSION                                       # → Phase 11B.2 v11B.2.1
php artisan test --filter=Phase11B21              # 38 v11B.2.1 scenarios
php artisan test                                   # 630 total (v11B.2.2's 45 scenarios removed)
```

The v11B.2.1 archive remains as the immutable Tier 3 baseline.

## Honest scope

✅ Defect A: CheckoutController emits per-line promotion-aware fields
✅ Defect B: Checkout/Show.tsx uses server-authoritative payable_minor
✅ Per dev §3: PricingService is the single canonical engine; new `priceProductWithQuantity` API matches dev's spec signature including calculation_at + pricing_version
✅ Per dev §4: integer minor units throughout; no floats in pricing path
✅ Per dev §5: server-side revalidation at checkout (CheckoutService::place re-runs PricingService)
✅ Per dev §6-§8: promotion eligibility hierarchy + coupon stacking documented and enforced
✅ Per dev §9-§10: multi-line + multi-vendor allocation via Phase 9 v9.3 mechanism (preserved)
✅ Per dev §15: order + order_item snapshots immutable across promotion deletion and product-price change (verified §26.4-5)
✅ Per dev §17: usage limits enforced (CouponValidator preserved)
✅ Per dev §20: refunds use stored amount (PaymentService preserved)
✅ Per dev §31: no client-submitted price trusted (§31.1-2)
✅ 45 Pest scenarios + 8 CI sub-checks

❌ NOT in v11B.2.2 (deferred — documented in REPORT "Remaining limitations"):
- Variant-specific promotion exclusions (schema lacks the table)
- Customer-segment / first-order promotions (schema lacks the columns)
- Multiple stackable promotions per product (current: best single)
- Bundle / Buy-X-Get-Y promotion types (enum + resolver rules not implemented)
- Free-shipping promotion consumption in shipping calculator
- Atomic concurrent coupon-limit reservation (pre-existing limitation)

## Phase 11B.2 v11B.2.2 STOPS HERE

No Phase 11B.3 work begun. **Pending dev verification** per directive §27 (manual verification across Summer Flash Sale + coupons + multi-vendor), §28 (reconciliation tables across all 8 surfaces), and §32 (commands).
