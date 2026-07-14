# Phase 10 v10.8 — Patch Notes

## What's fixed

One concrete defect from runtime testing.

| Defect | Root cause | Fix |
|---|---|---|
| Deals page advertised "Summer Flash Sale — 20% off all products" but the discount was not applied on /products, product detail, cart, checkout, stored orders, or financial breakdowns. Only the Deals page itself read promotions. | Phase 9 shipped `PromotionResolver` with correct logic (including the empty-targets ⇒ platform-wide branch), but the resolver was wired into NOBODY. `CatalogController` (listings + detail), `HomeController` (featured), `CartController::present`, `CheckoutService::place` all ignored promotions entirely. The class existed and worked; nothing called it. | (1) NEW `app/Domain/Pricing/PricingService.php` — canonical promotion-aware pricing API used by every consumer (bulk-resolves promotions in 1 query for listings, applies stacking-correct math for cart/checkout). (2) Wired into CatalogController, HomeController, CartController, CheckoutController, CheckoutService. (3) NEW migration adds promotion snapshot columns to `orders` + `order_items`. (4) Order + OrderItem `$fillable` extended. (5) React: Catalog/Index, Catalog/Show, Welcome, Cart/Show now display strikethrough original + final + "X% OFF" badge. (6) Coupon allocation across order_items uses POST-PROMOTION line totals so commission reconciles with what the customer actually paid. |

## Stacking order (dev §7)

```
subtotal → promotion → subtotal_after_promotion → coupon → payable
```

Example: 110 KWD subtotal, 20% promotion, 10% coupon:
- subtotal: 110.00
- promotion: 22.00
- subtotal_after_promotion: 88.00
- coupon (10% of 88): 8.80
- payable: 79.20

Pre-v10.8 the customer saw `110 − 11 (coupon on raw) = 99` with no promotion applied. v10.8 produces 79.20 matching the Deals page advertisement.

## Counts

| | v10.7 → v10.8 |
|---|---|
| Phase 10 CI sub-checks | 38 → 42 |
| Phase 10 Pest scenarios | 84 → 104 |
| Phase-specific CI grand total | 93 → 97 |
| New PHP source files | 2 (`PricingService`, migration) |
| Modified PHP source files | 7 (Order, OrderItem, CatalogController, HomeController, CartController, CheckoutController, CheckoutService) |
| Modified React files | 4 (Catalog/Index, Catalog/Show, Welcome, Cart/Show) |
| New Pest test files | 1 (20 scenarios) |
| v1-v9 files touched | 0 |
| v10.0-v10.7 fix code reverted | 0 |

## tsc verification

All 12 v10.x-touched React files type-check (exit code 0) against real `tsc 6.0.3`. New `data-testid` attributes (`catalog-card-promo-badge-*`, `product-promo-badge`, `cart-summary-promotion`, `home-featured-badge-*`, `cart-line-promoted-*`) added for the dev's manual UI verification.

## Per §O acceptance

**Phase 10 v10.8 is implemented but requires developer runtime verification.**

Per dev §17: "Do not say this is fixed until the same promotion is visibly and mathematically applied on products, cart, checkout, stored orders, and financial breakdowns." The dev's runtime walk-through is in `PHASE_10_v10.8_DEVELOPER_CHECKLIST.md`.
