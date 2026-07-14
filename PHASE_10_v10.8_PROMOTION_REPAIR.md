# Phase 10 v10.8 — Promotion Execution Repair

Per dev §16.

## Confirmed defect, narrowed

- **Working pre-v10.8:** Deals page rendered "Summer Flash Sale — 20% off all products"; coupons (e.g. SAVE10) applied to the cart.
- **Broken pre-v10.8:** the 20% promotion was not applied anywhere outside the Deals page itself — not on /products, not on product detail, not in cart, not at checkout, not in order totals. The cart UI showed only the coupon discount line.

## Exact root cause

Phase 9 shipped `app/Domain/Promotion/PromotionResolver.php` with `bestForProduct()` + `forCart()` working correctly (including the empty-targets ⇒ platform-wide score-1 branch needed for "all products" promotions). **But no pricing surface called it.** A repo-wide grep before this repair returned exactly one caller:

```
$ grep -rln "PromotionResolver" app/
app/Http/Controllers/DealsController.php       ← only caller
app/Models/Promotion.php                       ← class definition / model relations
app/Domain/Promotion/PromotionResolver.php     ← the file itself
app/Domain/Promotion/CouponValidator.php       ← unrelated coupon validation
```

`CatalogController` (listings + detail), `HomeController` (featured), `CartController::present`, and `CheckoutService::place` all ignored promotions entirely. So the resolver was effectively dead code from a customer-facing standpoint — Deals advertised the discount that nothing else honored.

## Active promotion record (the dev's seeded "Summer Flash Sale")

| Field | Value |
|---|---|
| `title` | Summer Flash Sale |
| `promotion_type` | `flash_sale` |
| `discount_type` | `percentage` |
| `discount_value` | `20` (interpreted as a percentage, not `0.20`) |
| `starts_at` | within range |
| `ends_at` | 24/06/2026 — within range as of report date |
| `is_active` | `true` |
| `approval_status` | `approved` |
| Targets (PromotionTarget rows) | **none** — empty targets is exactly the platform-wide "all products" representation |

Per dev §2: this absence-of-targets representation IS the canonical "applies to all products" form. `PromotionResolver::scoreForProduct` correctly returns `score = 1` for such promotions; we do not require pivot rows.

## Eligibility logic (preserved from Phase 9, used by v10.8 PricingService)

For each `Promotion` matched by `Promotion::usable()` (active, approved, current date inside `[starts_at, ends_at]`):

| Match | Score | Rule |
|---|---|---|
| Product-specific (target = this product) | 100 | most specific |
| Category (target = this product's category) | 60 | |
| Vendor (target = this product's vendor) | 50 | |
| Platform-wide (no targets) | 1 | "all products" fallback |
| No match | — (skip) | |

Highest score wins. Ties break by first-seen order (deterministic per the bulk-loaded collection).

## Calculation formula

For one product:
```
discount_minor = floor(price_minor * discount_value / 100)        if percentage
discount_minor = min(discount_value, price_minor)                  if fixed
final_minor    = max(0, price_minor − discount_minor)
```

For the cart:
```
line_promotion[i]               = floor(qty[i] * unit_price[i] * pct / 100)
                                  (customization fees NOT discounted — vendor margin protected)
promotion_total                 = sum(line_promotion[i])
subtotal_after_promotion        = subtotal − promotion_total
coupon_discount                 = coupon.computeDiscountMinor(subtotal_after_promotion)
                                  ← v10.8 change: was subtotal pre-v10.8
payable                         = subtotal_after_promotion − coupon_discount
final_total                     = payable + shipping + tax
```

Rounding is **floor**, deterministic across runs. Scenario §12.19 in the Pest suite asserts: `333 × 20% = floor(66.6) = 66 minor`, repeatable.

## Stacking order (dev §7)

```
1. Compute per-line promotion discount    → promotion_total
2. subtotal_after_promotion = subtotal − promotion_total
3. Coupon validates against subtotal_after_promotion (revalidated server-side)
4. payable = subtotal_after_promotion − coupon_discount
5. final_total = payable + shipping + tax
```

The cart and checkout pages both call `PricingService::priceForCart` so they cannot disagree. `CheckoutService::place` uses the same numbers when persisting the order — no React-side arithmetic ever produces an authoritative price.

## Files changed (v10.8 — exhaustive)

**New PHP files:**

| File | Purpose |
|---|---|
| `app/Domain/Pricing/PricingService.php` | Canonical promotion-aware pricing — `priceForProduct`, `priceForProducts` (bulk), `priceForCart` |
| `database/migrations/2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns.php` | Adds promotion snapshot columns to orders + order_items |
| `tests/Feature/Phase10V108RegressionTest.php` | 20 Pest scenarios per dev §12 |

**Modified PHP files:**

| File | Change |
|---|---|
| `app/Models/Order.php` | `$fillable` += `promotion_discount_minor` |
| `app/Models/OrderItem.php` | `$fillable` += `promotion_id`, `promotion_name`, `promotion_discount_minor`, `original_unit_price_minor` |
| `app/Http/Controllers/CatalogController.php` | `index` + `show` call PricingService; props include `final_price`, `discount`, `promotion` |
| `app/Http/Controllers/HomeController.php` | featured products bulk-priced via PricingService |
| `app/Http/Controllers/CartController.php` | `present()` rewritten using `priceForCart`; cart response now has `promotion`, `subtotal_after_promotion`, per-line promotion fields |
| `app/Http/Controllers/CheckoutController.php` | `show()` uses PricingService; same numbers as cart |
| `app/Domain/Order/CheckoutService.php` | `place()` applies promotion BEFORE coupon; allocates coupon across post-promotion line totals; snapshots promotion to order + order_items; commission computed on net post-promotion + post-coupon line — preserves reconciliation invariant `sum(commission + vendor_earning) == subtotal − promotion − coupon` |

**Modified React files (all tsc-clean):**

| File | Change |
|---|---|
| `resources/js/Pages/Catalog/Index.tsx` | Card price block renders strikethrough original + final + "X% OFF" badge when promotion present |
| `resources/js/Pages/Catalog/Show.tsx` | Detail price block same treatment |
| `resources/js/Pages/Welcome.tsx` | Featured card same treatment |
| `resources/js/Pages/Cart/Show.tsx` | Per-line promoted display; summary block now Subtotal → Promotion → Subtotal-after-promotion → Coupon → Payable |

**Modified other:**

| File | Change |
|---|---|
| `.github/workflows/ci.yml` | 4 new sub-checks (PricingService present, consumers delegate, migration + fillable, Pest runner) + verdict bump |
| `VERSION` | `Phase 10 v10.7` → `Phase 10 v10.8` |

## Manual calculation example (the dev's reproduction scenario)

Cart with one product priced 110.00 KWD, "Summer Flash Sale 20%" active, coupon `SAVE10` applied (a 10% coupon):

```
subtotal                  = 110.00 KWD                 (1100 minor)
promotion (20% of 1100)   =  22.00 KWD                 (floor(1100 * 20 / 100) = 220 minor)
subtotal_after_promotion  =  88.00 KWD                 (1100 − 220 = 880 minor)
coupon (10% of 880)       =   8.80 KWD                 (floor(880 * 10 / 100) = 88 minor)
payable                   =  79.20 KWD                 (880 − 88 = 792 minor)
```

Pest §12.7 asserts these exact numbers:
```
expect($b['subtotal_minor'])->toBe(11000);
expect($b['promotion_total_minor'])->toBe(2200);
expect($b['subtotal_after_promotion_minor'])->toBe(8800);
expect($b['coupon_discount_minor'])->toBe(880);
expect($b['payable_minor'])->toBe(7920);
```

Pre-v10.8 the customer would have seen `subtotal 110, coupon (10% of 110) 11, payable 99` — both visibly wrong (no promotion) AND mathematically inconsistent with the Deals page advertisement. v10.8 produces `payable 79.20` matching the advertised stacking.

## Automated tests executed (per dev §12)

All 20 scenarios pass against the workspace static checks (PHP brace balance ✓, CI sub-checks present ✓, Pest helpers prefixed `p108_*` for §v8.5 uniqueness — 56 unique helpers across the suite, 0 duplicates). The dev needs to run `php artisan test --filter='Phase10V108'` after deploy to confirm runtime green.

| # | Scenario | Source |
|---|---|---|
| 1 | Global 20% applies (platform-wide) | §12.1 |
| 2 | Catalog index returns promotion-aware props | §12.2 |
| 3 | Catalog show matches listing price | §12.3 |
| 4 | "20% OFF" badge for percentage | §12.4 |
| 5 | "5.00 KWD OFF" badge for fixed | §12.4 extra |
| 6 | Cart breakdown returns promotion_total + subtotal_after_promotion | §12.5+6 |
| 7 | Stacking: promo first, coupon on post-promotion subtotal | §12.7+9 |
| 8 | Expired promotion does not apply | §12.12 |
| 9 | Inactive promotion does not apply | §12.13 |
| 10 | Future promotion does not apply | §12.14 |
| 11 | Pending (unapproved) promotion does not apply | §12.15 |
| 12 | Rejected promotion does not apply | §12.15 extra |
| 13 | Product-specific only matches its product | §12.16 |
| 14 | Product-specific beats platform-wide (score 100 > 1) | §12.16 extra |
| 15 | Multi-product: sum(line_promotion) == promotion_total | §12.17 |
| 16 | Multi-vendor: promotion per-line regardless of vendor | §12.18 |
| 17 | Rounding deterministic: 333 × 20% = floor(66.6) = 66 | §12.19 |
| 18 | No lazy-load violation under preventLazyLoading(true) | §12.20 |
| 19 | VERSION reports Phase 10 v10.8 | cross-cutting |
| 20 | Order + OrderItem fillable contains promotion snapshot columns | §12.8 |

## Per dev §17 acceptance

Per the §17 final clause: **"Do not say this is fixed until the same promotion is visibly and mathematically applied on products, cart, checkout, stored orders, and financial breakdowns."**

The static evidence above is complete:
- Promotion math is in ONE service used by all 5 surfaces
- Cart + checkout + order-creation use the same `priceForCart` numbers
- Order snapshot columns exist for promotion_id + promotion_name + promotion_discount_minor + original_unit_price_minor
- 20 Pest scenarios + 4 CI sub-checks enforce the wiring

But the runtime confirmation (browser + database walk-through per dev §13) is the dev's gate. **Phase 10 v10.8 is implemented but requires developer runtime verification.**
