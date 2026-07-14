<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cart\CartService;
use App\Domain\Commission\CommissionResolver;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Converts a cart + checkout selections into a persisted Order.
 *
 * Responsibilities:
 *  - Validate cart contents + selected addresses + payment method
 *  - Snapshot product names/SKUs/variants onto order_items so the line
 *    survives later product mutation/deletion
 *  - Snapshot commission via CommissionResolver — commission rule changes
 *    later do NOT affect already-placed orders
 *  - Set the 7-day earnings hold (Phase 2 locked decision: release 7 days
 *    after delivery)
 *  - Emit an order.placed event row
 *  - Clear the cart on success
 *
 * Payment is NOT taken here. PaymentService runs immediately after, in the
 * controller — separating these two transactions means a payment failure
 * leaves an unpaid order (recoverable) rather than corrupting cart state.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CommissionResolver $commissions,
        private readonly OrderNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Place an order from the given user's cart.
     *
     * @param array{
     *   shipping_address_id?: int,
     *   billing_address_id?: int,
     *   shipping_address?: array,
     *   billing_address?: array,
     *   payment_method_slug: string,
     *   customer_notes?: string,
     *   shipping_minor?: int,
     * } $checkout
     */
    public function place(User $user, array $checkout): Order
    {
        $cart = $this->carts->getOrCreate($user);

        // v5.7 — eager-load the relations the order-items loop reads.
        // v5.8 — switched from loadMissing to load() so the chain is rebuilt
        // even if `items` was partially loaded by some upstream path (e.g.
        // the Inertia shared `cart_summary` accessor in a previous render);
        // loadMissing is a no-op when `items` is "loaded" even if individual
        // items lack `product.vendor` deeper in the chain. Belt-and-suspenders:
        // we ALSO defensively loadMissing inside each loop iteration below.
        $cart->load([
            'items.product.vendor.activeSubscription.package',
            'items.product.category',
            'items.variant',
            // Phase 7 — needed so the order_item snapshot loop can copy each
            // customization row into order_item_customizations without lazy-load.
            'items.customizations',
        ]);

        if ($cart->isEmpty()) {
            throw new RuntimeException('Your cart is empty.');
        }

        $method = PaymentMethod::where('slug', $checkout['payment_method_slug'] ?? '')
            ->where('is_active', true)
            ->where('available_at_checkout', true)
            ->first();
        if (! $method) {
            throw new RuntimeException('Selected payment method is not available.');
        }
        if (! $method->supportsCurrency($cart->currency)) {
            throw new RuntimeException("Payment method {$method->slug} does not support {$cart->currency}.");
        }

        $shippingAddr = $this->resolveAddress($user, $checkout, 'shipping');
        $billingAddr  = $this->resolveAddress($user, $checkout, 'billing') ?? $shippingAddr;

        return DB::transaction(function () use ($user, $cart, $method, $shippingAddr, $billingAddr, $checkout) {
            // Re-validate every line — product might've been unpublished
            // between cart-add and checkout; price might've changed; etc.
            foreach ($cart->items as $item) {
                if (! $item->product || $item->product->status !== Product::STATUS_PUBLISHED) {
                    throw new RuntimeException("'{$item->product?->name}' is no longer available.");
                }
                if ($item->variant && ! $item->variant->is_active) {
                    throw new RuntimeException("A variant of '{$item->product->name}' is no longer available.");
                }
            }

            $subtotal = $cart->subtotal_minor;

            // Phase 5 — resolve shipping from the chosen ShippingMethod if
            // provided. Falls back to the raw shipping_minor (Phase 4 behavior)
            // when no method is selected, so existing checkout payloads stay
            // compatible.
            $shippingMethod     = null;
            $shippingMethodName = null;
            if (! empty($checkout['shipping_method_id'])) {
                $shippingMethod = \App\Models\ShippingMethod::find($checkout['shipping_method_id']);
            }
            if ($shippingMethod) {
                $shipping           = $shippingMethod->feeFor($subtotal);
                $shippingMethodName = $shippingMethod->name;
            } else {
                $shipping = (int) ($checkout['shipping_minor'] ?? 0);
            }

            $tax      = 0;  // Phase 5+ — tax engine

            // Phase 10 v10.8 — promotion before coupon (dev §7 stacking).
            // Compute the per-line promotion breakdown via PricingService;
            // we'll reuse the per-line numbers for the order_items snapshot
            // loop below.
            $pricing = app(\App\Domain\Pricing\PricingService::class);
            $cartBreakdown = $pricing->priceForCart($cart, $user);
            $promotionTotal = (int) $cartBreakdown['promotion_total_minor'];
            $subtotalAfterPromotion = (int) $cartBreakdown['subtotal_after_promotion_minor'];

            // Build a per-cart-item promotion lookup keyed by cart_item_id so
            // the order_item creation loop below can snapshot promotion_id,
            // promotion_name, promotion_discount_minor, original_unit_price_minor.
            $promotionByCartItem = [];
            foreach ($cartBreakdown['lines'] as $ln) {
                $promotionByCartItem[$ln['cart_item_id']] = $ln;
            }

            // Phase 9 v9.1 — coupon discount. Re-validate server-side and
            // compute against the POST-PROMOTION subtotal (v10.8 stacking).
            // If the coupon is no longer usable, drop it silently.
            $couponId = null;
            $couponCode = null;
            $couponDiscount = 0;
            if ($cart->coupon_id) {
                $couponResult = \App\Domain\Promotion\CouponValidator::validate(
                    (string) ($cart->coupon?->code ?? ''),
                    $cart,
                    $user,
                );
                if ($couponResult['ok'] && $couponResult['coupon']) {
                    $couponId = $couponResult['coupon']->id;
                    $couponCode = $couponResult['coupon']->code;
                    // v10.8: coupon discount computed on subtotal AFTER promotion
                    $couponDiscount = (int) $couponResult['coupon']->computeDiscountMinor($subtotalAfterPromotion);
                }
            }
            $discount = $couponDiscount;
            $total    = max(0, $subtotalAfterPromotion + $shipping + $tax - $discount);

            $order = Order::create([
                'number'               => $this->numbers->next(),
                'user_id'              => $user->id,
                'status'               => Order::STATUS_PENDING_PAYMENT,
                'payment_status'       => Order::PAY_PENDING,
                'fulfillment_status'   => Order::FUL_UNFULFILLED,
                'currency'             => $cart->currency,
                'shipping_method_id'   => $shippingMethod?->id,
                'shipping_method_name' => $shippingMethodName,
                'subtotal_minor'       => $subtotal,
                'shipping_minor'       => $shipping,
                'tax_minor'            => $tax,
                'discount_minor'       => $discount,
                'total_minor'          => $total,
                'customer_notes'       => $checkout['customer_notes'] ?? null,
                // Phase 9 v9.1 — coupon snapshot.
                'coupon_id'             => $couponId,
                'coupon_discount_minor' => $couponDiscount,
                'coupon_code'           => $couponCode,
                // Phase 10 v10.8 — promotion snapshot (order-level sum)
                'promotion_discount_minor' => $promotionTotal,
            ]);

            // Phase 9 v9.1 — record coupon usage so the per-user limit is
            // enforced on the NEXT order. Skip if the coupon was dropped above.
            if ($couponId) {
                \App\Models\CouponUsage::create([
                    'coupon_id'      => $couponId,
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'discount_minor' => $couponDiscount,
                    'currency'       => $cart->currency,
                    'used_at'        => now(),
                ]);
            }

            // Snapshot addresses onto the order
            $this->snapshotAddress($order, 'shipping', $shippingAddr);
            $this->snapshotAddress($order, 'billing',  $billingAddr);

            // Create order_items with snapshots + commission resolution per line
            $platformTotal = 0;
            $vendorTotal   = 0;

            // Phase 9 v9.3 + Phase 10 v10.8 — coupon allocation across lines.
            // Pre-v10.8 used GROSS line totals. v10.8 uses POST-PROMOTION line
            // totals so each line's share of the coupon is proportional to
            // what the customer actually paid for that line (gross − promo).
            //
            // Allocation rule (documented):
            //   line_after_promo[i] = line_total[i] - line_promotion[i]
            //   allocated[i] = floor(coupon_discount * line_after_promo[i] / subtotal_after_promotion)
            //
            // for lines 0..n-2; the last line receives the remainder so the
            // sum equals exactly couponDiscount (deterministic rounding).
            //
            // Invariants the tests assert:
            //   sum(line_promotion)        === promotionTotal
            //   sum(allocated)             === couponDiscount
            //   sum(net_line)              === subtotal − promotion − coupon
            //   sum(commission + earning)  === sum(net_line)
            $cartItemsArr = $cart->items->values();
            $lineCount    = $cartItemsArr->count();
            $allocations  = [];
            $allocatedSoFar = 0;
            for ($i = 0; $i < $lineCount; $i++) {
                $line = $promotionByCartItem[$cartItemsArr[$i]->id] ?? null;
                $linePostPromo = $line ? (int) $line['line_final_minor'] : $cartItemsArr[$i]->lineTotalMinor();
                if ($i === $lineCount - 1) {
                    $allocations[$i] = $couponDiscount > 0
                        ? max(0, $couponDiscount - $allocatedSoFar)
                        : 0;
                } else {
                    $allocations[$i] = $subtotalAfterPromotion > 0 && $couponDiscount > 0
                        ? (int) floor($couponDiscount * $linePostPromo / $subtotalAfterPromotion)
                        : 0;
                    $allocatedSoFar += $allocations[$i];
                }
            }

            $itemIndex = 0;
            foreach ($cart->items as $cartItem) {
                // v5.8 — defensive per-item eager-load.
                $cartItem->loadMissing([
                    'product.vendor.activeSubscription.package',
                    'product.category',
                    'variant',
                ]);

                /** @var Product $product */
                $product = $cartItem->product;
                /** @var Vendor $vendor */
                $vendor  = $product->vendor;

                $lineTotal = $cartItem->lineTotalMinor();

                // Phase 10 v10.8 — per-line promotion snapshot from the breakdown
                $lineMeta = $promotionByCartItem[$cartItem->id] ?? null;
                $linePromotionMinor = $lineMeta ? (int) $lineMeta['line_promotion_minor'] : 0;
                $linePromotionId    = $lineMeta['promotion']['id']    ?? null;
                $linePromotionName  = $lineMeta['promotion']['title'] ?? null;
                $unitFinalMinor     = $lineMeta ? (int) $lineMeta['unit_final_minor'] : (int) $cartItem->unit_price_minor;
                $originalUnitMinor  = $linePromotionMinor > 0 ? (int) $cartItem->unit_price_minor : null;
                $linePostPromo      = $lineTotal - $linePromotionMinor;

                // Phase 9 v9.3 + v10.8 — coupon allocation now derived from
                // POST-PROMOTION line totals (see allocation loop above).
                $allocatedCoupon = $allocations[$itemIndex] ?? 0;
                // Net line for commission = line − promotion − coupon
                $netLineTotal    = max(0, $linePostPromo - $allocatedCoupon);

                // Resolve commission AT ORDER TIME and snapshot it. Later rule
                // changes don't retroactively touch this order.
                $rule = $this->commissions->forProduct($product);

                $commissionPercent = $rule?->percent_value ?? $vendor->currentPackage()?->default_admin_commission_percent ?? 0;
                $commissionAmount  = (int) round(($netLineTotal * (float) $commissionPercent) / 100);
                $vendorEarning     = max(0, $netLineTotal - $commissionAmount);

                $orderItem = OrderItem::create([
                    'order_id'              => $order->id,
                    'vendor_id'             => $vendor->id,
                    'product_id'            => $product->id,
                    'variant_id'            => $cartItem->variant_id,
                    'product_name'          => $product->name,
                    'product_sku'           => $product->sku,
                    'variant_name'          => $cartItem->variant?->name,
                    'variant_attributes'    => $cartItem->variant?->attribute_values,
                    'quantity'              => $cartItem->quantity,
                    // unit_price_minor now reflects the POST-PROMOTION unit price,
                    // matching what the customer actually paid per unit
                    'unit_price_minor'      => $unitFinalMinor,
                    'line_total_minor'      => $linePostPromo,
                    // Phase 10 v10.8 — promotion snapshot
                    'promotion_id'              => $linePromotionId,
                    'promotion_name'            => $linePromotionName,
                    'promotion_discount_minor'  => $linePromotionMinor,
                    'original_unit_price_minor' => $originalUnitMinor,
                    // Phase 9 v9.3 — per-line coupon allocation (post-promo)
                    'coupon_allocation_minor' => $allocatedCoupon,
                    'currency'              => $cartItem->currency,
                    'commission_percent'    => $commissionPercent,
                    'commission_amount_minor' => $commissionAmount,
                    'vendor_earning_minor'  => $vendorEarning,
                    // Phase 6 — snapshot supplier_cost on the line so margin
                    // calculations are deterministic regardless of later supplier
                    // price changes. Null for non-dropship products.
                    'supplier_cost_minor'   => $product->isDropship() ? (int) ($product->supplier_cost_minor ?? 0) : null,
                    // Phase 7 — snapshot customization fee + default status.
                    'customization_fee_minor' => (int) ($cartItem->customization_fee_minor ?? 0),
                    'customization_status'  => OrderItem::CUST_PENDING,
                    'fulfillment_status'    => OrderItem::FUL_UNFULFILLED,
                ]);
                $itemIndex++;

                // Phase 7 — snapshot the per-field customization rows. The cart
                // rows live in cart_item_customizations and disappear with the
                // cart; the order rows live in order_item_customizations and
                // are immutable history.
                foreach ($cartItem->customizations as $cic) {
                    \App\Models\OrderItemCustomization::create([
                        'order_item_id'      => $orderItem->id,
                        'field_key'          => $cic->field_key,
                        'field_label'        => $cic->field_label,
                        'field_type'         => $cic->field_type,
                        'value'              => $cic->value,
                        'file_path'          => $cic->file_path,
                        'file_original_name' => $cic->file_original_name,
                        'file_mime'          => $cic->file_mime,
                        'file_size_bytes'    => $cic->file_size_bytes,
                        'extra_fee_minor'    => $cic->extra_fee_minor,
                    ]);
                }

                $platformTotal += $commissionAmount;
                $vendorTotal   += $vendorEarning;
            }

            $order->update([
                'platform_commission_minor' => $platformTotal,
                'vendor_earnings_minor'     => $vendorTotal,
            ]);

            // Phase 6 — create supplier_order rows for any dropshipping items.
            // Idempotent: groups by (vendor, supplier_platform) so multiple items
            // going to the same supplier roll into one supplier_order.
            app(\App\Domain\Supplier\DropshipOrderCreator::class)->createFromOrder($order);

            // Decrement product stock immediately on order placement.
            // (Reservation pattern with rollback on payment failure is Phase 5+ —
            //  for Phase 4 we trade a small over-sell risk on payment-failure
            //  scenarios for simpler bookkeeping.)
            foreach ($cart->items as $cartItem) {
                if ($cartItem->variant) {
                    $cartItem->variant->decrement('stock', $cartItem->quantity);
                } elseif ($cartItem->product->track_stock) {
                    $cartItem->product->decrement('stock', $cartItem->quantity);
                }
            }

            // Audit + event log
            $order->events()->create([
                'event_type' => 'placed',
                'message'    => "Order placed via {$method->slug}.",
                'actor_id'   => $user->id,
                'actor_role' => 'customer',
                'payload'    => ['payment_method' => $method->slug],
            ]);
            $this->audit->log('order.placed', $order, after: [
                'total_minor'   => $order->total_minor,
                'currency'      => $order->currency,
                'items_count'   => $cart->items_count,
            ]);

            // Empty the cart now that we've taken responsibility for it
            $this->carts->clear($user);

            // v7.6 — eager-load customizations + latestProof on the returned
            // order so any caller (PaymentService::initiateFor, listeners,
            // post-checkout redirects) can safely iterate items->customizations
            // / items->latestProof under Model::shouldBeStrict() without
            // tripping the lazy-load detector.
            return $order->fresh(['items', 'items.customizations', 'items.latestProof', 'addresses', 'events']);
        });
    }

    /**
     * Customer picks an existing Address row, OR provides ad-hoc address
     * fields. The latter is useful for one-off addresses.
     *
     * v5.2 — both code paths now produce a normalized array with the Phase 1
     * Gulf-style fields (block/street/building/floor/apartment + governorate
     * as `state`). `recipient_name` defaults to the user's account name when
     * not explicitly supplied.
     *
     * @return array{recipient_name:string,phone:?string,country:string,state:?string,city:string,area:?string,block:?string,street:?string,building:?string,floor:?string,apartment:?string,postal_code:?string,latitude:?string,longitude:?string}|null
     */
    private function resolveAddress(User $user, array $checkout, string $type): ?array
    {
        $idKey = "{$type}_address_id";
        $inlineKey = "{$type}_address";

        if (! empty($checkout[$idKey])) {
            /** @var Address|null $addr */
            $addr = $user->addresses()->where('id', $checkout[$idKey])->first();
            if (! $addr) {
                throw new RuntimeException(ucfirst($type) . ' address not found.');
            }
            return [
                'recipient_name' => $user->name,        // implicit recipient
                'phone'          => $addr->phone,
                'country'        => $addr->country,
                'state'          => $addr->state,
                'city'           => $addr->city,
                'area'           => $addr->area,
                'block'          => $addr->block,
                'street'         => $addr->street,
                'building'       => $addr->building,
                'floor'          => $addr->floor,
                'apartment'      => $addr->apartment,
                'postal_code'    => $addr->postal_code,
                'latitude'       => $addr->latitude !== null ? (string) $addr->latitude : null,
                'longitude'      => $addr->longitude !== null ? (string) $addr->longitude : null,
            ];
        }

        if (! empty($checkout[$inlineKey])) {
            $inline = $checkout[$inlineKey];
            // Inline path: validation already enforced city + country; everything
            // else is optional. Default recipient_name to the user if blank.
            return [
                'recipient_name' => $inline['recipient_name'] ?? $user->name,
                'phone'          => $inline['phone']        ?? null,
                'country'        => $inline['country'],
                'state'          => $inline['state']        ?? null,
                'city'           => $inline['city'],
                'area'           => $inline['area']         ?? null,
                'block'          => $inline['block']        ?? null,
                'street'         => $inline['street']       ?? null,
                'building'       => $inline['building']     ?? null,
                'floor'          => $inline['floor']        ?? null,
                'apartment'      => $inline['apartment']    ?? null,
                'postal_code'    => $inline['postal_code']  ?? null,
                'latitude'       => null,
                'longitude'      => null,
            ];
        }

        if ($type === 'shipping') {
            throw new RuntimeException('A shipping address is required.');
        }
        return null; // billing can fall back to shipping
    }

    private function snapshotAddress(Order $order, string $type, array $data): void
    {
        $order->addresses()->create([
            'type'           => $type,
            'recipient_name' => $data['recipient_name'],
            'phone'          => $data['phone'] ?? null,
            'country'        => $data['country'],
            'state'          => $data['state'] ?? null,
            'city'           => $data['city'],
            'area'           => $data['area'] ?? null,
            'block'          => $data['block'] ?? null,
            'street'         => $data['street'] ?? null,
            'building'       => $data['building'] ?? null,
            'floor'          => $data['floor'] ?? null,
            'apartment'      => $data['apartment'] ?? null,
            'postal_code'    => $data['postal_code'] ?? null,
            'latitude'       => $data['latitude'] ?? null,
            'longitude'      => $data['longitude'] ?? null,
        ]);
    }
}
