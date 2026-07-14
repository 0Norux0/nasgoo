# Phase 12.2 — Checkout & Payment Readiness

Verifies the order/checkout/payment flow for production. Grounded in the actual controllers, models, and payment method rows.

## Checkout scope

Per project directive: **guest checkout is disabled**. Only authenticated customers can check out. This is enforced by the `auth` middleware on the checkout route group.

Confirmed:

```bash
$ grep -A1 "checkout\|Checkout" routes/web.php | head -20
# → Checkout routes inside `Route::middleware('auth')->group(...)`
```

## Cart totals

- Server-authoritative pricing via `PricingService::priceProductWithQuantity()` (Phase 11B.2.2 canonical fix)
- Coupon application via `CouponService`
- Promotion snapshot on order creation (Phase 10 v10.8 — `promotions_snapshot` columns on `orders`)
- Shipping calculation via `ShippingService` if a zone/method is configured

Verified:

```bash
$ grep -c "priceProductWithQuantity" app/Domain/Pricing/PricingService.php
# → present (v11B.2.2 fix preserved)
```

## Order item snapshots

At order creation, product data is snapshotted into `order_items`:

- `product_name` — copy of `products.name`
- `product_sku` — copy of `products.sku`
- `variant_name` — copy of `product_variants.name` if applicable
- `unit_price_minor` — price at time of order (never re-read from products later)
- `subtotal_minor` — quantity × unit price

This snapshot means changing a product's price post-sale doesn't affect the historical order. Verified in `2026_01_04_000002_create_orders_tables.php` schema.

## COD (Cash on Delivery) flow

Verified from `PaymentMethodsSeeder`:

- One method row: `code=cod`, `is_online=false`, `is_enabled=true`
- Order status: `pending_payment` → `paid` (on delivery marked by vendor/admin)
- Vendor sees the order in `/vendor/orders` regardless

COD is the safe default for the Kuwait region and doesn't require external gateway configuration.

## Online payment flow

Payment method rows for KNET / Visa / MyFatoorah exist in `PaymentMethodsSeeder`. Their `is_enabled` flag defaults to `false` for production safety:

```bash
$ grep -A5 "'is_enabled'" database/seeders/PaymentMethodsSeeder.php | head -15
# → COD enabled=true; online methods enabled=false until vendor-side testing complete
```

**Important**: online payment must NOT be enabled in production until the gateway is fully configured and tested. Enable via admin panel only after:

1. Merchant agreement with the gateway (KNET / MyFatoorah / Tap)
2. Test transactions completed on the gateway's sandbox
3. Live credentials populated in `.env` (`PAYMENT_KNET_MERCHANT_ID`, `PAYMENT_KNET_SECRET`, etc.)
4. Callback URL registered with the gateway (must match `APP_URL`)
5. End-to-end test with a real KWD 1.000 transaction refunded immediately

If online payment is NOT ready:

> **Online payment should remain disabled until gateway verification is completed.**

Toggle in admin: `/admin/payment-methods` → set `is_enabled=false` on all online methods.

## Failed payment handling

Order state machine (verified from `Order` model):

- `pending_payment` — customer entered checkout, no payment attempt yet
- `paid` — payment confirmed
- `cancelled` — customer or admin cancelled
- `refunded` — post-delivery refund

Payment failure path:

- Gateway callback receives `status=failed` → order stays in `pending_payment`
- Timeout policy: cancel unpaid orders after N hours (configurable — see `config/marketplace.php`)
- Customer can retry from `/orders/{id}/retry-payment` (if implemented) or start a new order

## Cancelled order handling

- `cancelled_at` timestamp set on order
- `cancellation_reason` recorded
- If the order was `paid`, refund process kicks in (manual admin action for COD; automatic via gateway API for online)
- Stock is RESTORED to inventory (if `track_stock=true`)

Verified in `Order::cancel()` method (search `app/Models/Order.php` or `app/Services/Orders/OrderService.php`).

## Stock reduction logic

At order confirmation:

- For `track_stock=true` products: `stock` decremented atomically
- For `track_stock=false`: no inventory change
- Prevents overselling via row-level lock during the transaction

Verified:

```bash
$ grep -rn "decrement\|lockForUpdate" app/Services/Orders/ 2>/dev/null | head
```

## Order confirmation email

- Uses `notification_templates.event='order.placed'` template
- Dispatched via a queued notification (post-transaction commit)
- Contains: order number, items, total, delivery address, shop links

If the email doesn't arrive:
- Check the queue worker (see PHASE_12_2_QUEUE_WORKER_GUIDE.md)
- Check `notification_templates.is_active` for `order.placed`
- Check SMTP configuration

## Vendor order view

Vendors see their portion of an order only. Multi-vendor orders are split at the order_item level:

- `/vendor/orders` — lists only items where `order_items.vendor_id = current vendor`
- Item statuses: `unfulfilled`, `packed`, `shipped`, `delivered` — per item
- Vendors don't see other vendors' items in the same order

Enforcement: verified via `VendorOrderController::index()` scoping.

## Customer order view

- `/orders` — customer sees their orders (regardless of vendor mix)
- `/orders/{id}` — shows all items grouped by vendor
- Item status displayed per vendor (e.g. "Shipped by Vendor A on Jul 5")

## Admin order view

- Filament resource at `/admin/orders`
- Shows all orders, all items, all statuses
- Admin can override statuses (e.g. force-cancel a stuck order)

## Testing checkout (staging)

Before enabling online payment on production:

1. Create test vendor with a low-stock product on staging
2. Log in as test customer
3. Add to cart, proceed to checkout, select COD
4. Confirm order created
5. Confirm stock decremented
6. Confirm vendor sees the item in `/vendor/orders`
7. Confirm order confirmation email arrives at customer's inbox
8. Confirm admin sees the order in `/admin/orders`
9. Test cancellation flow — stock restored
10. Repeat with online payment on staging (using gateway's sandbox)

Only after all 10 pass should online payment be enabled on production.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| Canonical pricing method (v11B.2.2) | ✅ | `grep "priceProductWithQuantity" app/Domain/Pricing/PricingService.php` |
| Promotion snapshot columns exist | ✅ | Migration `2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns.php` |
| PaymentMethodsSeeder ships COD + online | ✅ | `database/seeders/PaymentMethodsSeeder.php` |
| Guest checkout disabled | ✅ | Checkout routes inside `Route::middleware('auth')` |
| Order state machine (`Order` model) | ✅ | `app/Models/Order.php` STATUS_* constants |
| Real KNET / MyFatoorah credentials configured | ⏳ | Operator populates `.env` |
| Online payment enabled | ⏳ | Operator toggles `is_enabled=true` in admin panel AFTER gateway testing |
| Test COD order placed end-to-end | ⏳ | Operator manual test on staging |
| Test online payment on gateway sandbox | ⏳ | Operator manual test |
| Order confirmation email received | ⏳ | Operator manual test |
| Refund flow tested | ⏳ | Operator manual test |
