<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Order\CheckoutService;
use App\Domain\Payment\PaymentService;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Single-page checkout. The page renders address picker, shipping (flat for
 * Phase 4 — `shipping_minor` is a per-order constant), payment method choice,
 * review and place button. Place uses Inertia POST to /checkout, which:
 *   1) CheckoutService.place — creates the Order (and clears the cart)
 *   2) PaymentService.initiateFor — creates the Payment, asks provider to start
 *   3) Redirects to provider redirectUrl (online), or to /orders/{id}/confirm
 *      (sync providers like COD/manual_transfer/online_mock)
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Render the single-page checkout.
     *
     * Returns either an Inertia\Response (page render) OR a RedirectResponse
     * (when the cart is empty we bounce to /cart). Inertia\Response is NOT a
     * Symfony Response subclass (it's a Responsable), so we MUST use a union
     * here — typing this as Symfony\Component\HttpFoundation\Response was the
     * v5.0–v5.2 TypeError that broke the checkout page on first load.
     */
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();
        $cart = $this->carts->getOrCreate($user);

        // v5.7 — eager-load every relation the checkout summary touches.
        // Without this, multi-item carts hit the same lazy-load N+1 strict-mode
        // error that CheckoutService::place() suffered from.
        $cart->load([
            'items.product.primaryImage',
            'items.vendor',
            'items.variant',
        ]);
        $cart->load(['items.product:id,name,slug,vendor_id', 'items.product.primaryImage:id,product_id,path',
                     'items.variant:id,name', 'items.vendor:id,business_name']);

        if ($cart->isEmpty()) {
            return redirect('/cart')->with('error', 'Your cart is empty.');
        }

        // Phase 9 v9.3 — checkout-step coupon re-validation. If the cart has
        // a stored coupon but it's no longer usable (eg. expired since being
        // applied), we drop it silently here so the checkout summary shows
        // the same state CheckoutService will see at order placement. This
        // prevents the "cart shows 50 KWD discount but order saved without
        // it" surprise.
        if ($cart->coupon_id) {
            $cart->load('coupon:id,code,discount_type,discount_value,currency');
            $result = \App\Domain\Promotion\CouponValidator::validate(
                (string) ($cart->coupon?->code ?? ''),
                $cart,
                $user,
            );
            if (! ($result['ok'] && $result['coupon'])) {
                // Stale coupon — silently drop from cart
                $cart->update(['coupon_id' => null, 'discount_minor' => 0]);
                $cart->load('coupon');
            }
        }

        // Phase 10 v10.8 — canonical breakdown via PricingService. SAME numbers
        // the cart page shows and the same numbers CheckoutService::place will
        // persist to the order. One source of truth, validated server-side.
        $breakdown = app(\App\Domain\Pricing\PricingService::class)->priceForCart($cart, $user);
        $couponBlock = $breakdown['coupon_id'] ? [
            'code'           => $breakdown['coupon_code'],
            'discount'       => $breakdown['coupon_discount'],
            'discount_minor' => $breakdown['coupon_discount_minor'],
        ] : null;
        $payableMinor = (int) $breakdown['payable_minor'];

        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->where('available_at_checkout', true)
            ->orderBy('position')
            ->get()
            ->filter(fn ($m) => $m->supportsCurrency($cart->currency))
            ->map(fn ($m) => [
                'slug'        => $m->slug,
                'name'        => $m->translatedName(),
                'description' => $m->description,
            ])
            ->values();

        return Inertia::render('Checkout/Show', [
            'cart' => [
                'currency'       => $cart->currency,
                'subtotal'       => $breakdown['subtotal'],
                'subtotal_minor' => $breakdown['subtotal_minor'],
                'items_count'    => $cart->items_count,
                // Phase 10 v10.8 — promotion total (null when no promotion applies)
                'promotion'      => $breakdown['promotion_total_minor'] > 0 ? [
                    'discount'       => $breakdown['promotion_total'],
                    'discount_minor' => $breakdown['promotion_total_minor'],
                ] : null,
                'subtotal_after_promotion'       => $breakdown['subtotal_after_promotion'],
                'subtotal_after_promotion_minor' => $breakdown['subtotal_after_promotion_minor'],
                // Phase 9 v9.3 — coupon block + payable amount so the checkout
                // summary shows the same numbers as the cart page.
                'coupon'         => $couponBlock,
                'payable'        => $breakdown['payable'],
                'payable_minor'  => $payableMinor,
                // Phase 11B.2 v11B.2.2 §A — per-line promotion-aware fields.
                // Before v11B.2.2, this map emitted only `unit_price` and
                // `line_total` (the pre-promotion values from cart_items.unit_price_minor).
                // The React Checkout/Show page then displayed those raw line
                // values to the customer, producing a visible mismatch:
                // cart page showed 80.000 KWD per unit, checkout page showed
                // 100.000 KWD per unit, even though the underlying server-side
                // order creation (CheckoutService::place → PricingService) was
                // already correct. This index() now mirrors CartController's
                // index() exactly: the breakdown's per-cart-item lookup
                // populates unit_price_final, line_total_final, line_promotion
                // (label), and the promotion meta block. One source of truth
                // (PricingService::priceForCart). Cart and Checkout are now
                // BIT-FOR-BIT identical in their per-line output shape.
                'items' => (function () use ($cart, $breakdown) {
                    // Index the breakdown lines by cart_item_id for O(1) lookup
                    $byCartItem = [];
                    foreach ((array) ($breakdown['lines'] ?? []) as $ln) {
                        $byCartItem[$ln['cart_item_id']] = $ln;
                    }
                    return $cart->items->map(function ($i) use ($byCartItem) {
                        $line = $byCartItem[$i->id] ?? null;
                        return [
                            'id'                 => $i->id,
                            'product_name'       => $i->product?->name,
                            'variant_name'       => $i->variant?->name,
                            'vendor_name'        => $i->vendor?->business_name,
                            'quantity'           => $i->quantity,
                            'unit_price'         => number_format($i->unit_price_minor / 100, 2),
                            'line_total'         => number_format($i->lineTotalMinor() / 100, 2),
                            // v11B.2.2 §A — promotion-aware values. Equal to the
                            // pre-promotion values when no promotion applies, so
                            // React can render unit_price_final unconditionally.
                            'unit_price_final'   => $line['unit_final']
                                ?? number_format($i->unit_price_minor / 100, 2),
                            'line_total_final'   => $line['line_final']
                                ?? number_format($i->lineTotalMinor() / 100, 2),
                            'line_promotion'     => $line['line_promotion'] ?? null,
                            'promotion'          => $line['promotion'] ?? null,
                            'thumb'              => $i->product?->primaryImage?->url,
                        ];
                    })->values();
                })(),
            ],
            // v5.2 — select the actual Phase 1 columns. The pre-v5.2 column
            // list (full_name / line1 / line2 / region) didn't exist and
            // caused a SQL error on the checkout page render.
            'addresses' => $user->addresses()
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get([
                    'id', 'label', 'type', 'country', 'state', 'city',
                    'area', 'block', 'street', 'building', 'floor', 'apartment',
                    'postal_code', 'phone', 'is_default',
                ])
                ->map(fn ($a) => [
                    'id'           => $a->id,
                    'label'        => $a->label,
                    'recipient_name' => $user->name,    // implicit recipient
                    'phone'        => $a->phone,
                    'country'      => $a->country,
                    'state'        => $a->state,
                    'city'         => $a->city,
                    'area'         => $a->area,
                    'block'        => $a->block,
                    'street'       => $a->street,
                    'building'     => $a->building,
                    'floor'        => $a->floor,
                    'apartment'    => $a->apartment,
                    'postal_code'  => $a->postal_code,
                    'is_default'   => $a->is_default,
                    'single_line'  => $a->fullAddressLine(),  // pre-formatted for display
                ]),
            'payment_methods'  => $methods,

            // Phase 5 — shipping methods available for the user's default
            // shipping country (falls back to KW). The frontend renders a
            // method picker; the chosen method's fee + slug travel with the
            // POST /checkout payload. If no zone is configured, the array is
            // empty and checkout falls back to the legacy flat shipping_minor.
            'shipping_methods' => (function () use ($user, $cart) {
                $defaultAddr = $user->addresses()->where('is_default', true)->first();
                $country = $defaultAddr?->country ?? 'KW';
                $region  = $defaultAddr?->state ?? $defaultAddr?->city ?? null;
                $methods = app(\App\Domain\Shipping\ShippingResolver::class)
                    ->availableFor($country, $region, $cart->subtotal_minor);
                return $methods->map(fn (\App\Models\ShippingMethod $m) => [
                    'id'        => $m->id,
                    'slug'      => $m->slug,
                    'name'      => $m->name,
                    'type'      => $m->type,
                    'fee'       => number_format($m->feeFor($cart->subtotal_minor) / 100, 3),
                    'fee_minor' => $m->feeFor($cart->subtotal_minor),
                    'currency'  => $m->currency,
                    'eta_label' => $m->eta_label,
                    'description' => $m->description,
                ])->values();
            })(),

            'default_shipping_minor' => 0,
            'has_addresses' => $user->addresses()->exists(),
            'user_name'     => $user->name,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        // v5.2 — validation rules match the actual Phase 1 addresses schema
        // (block/street/building/floor/apartment + governorate as `state`,
        // no full_name — recipient_name defaults to the user's account name
        // unless explicitly overridden).
        $data = $request->validate([
            'shipping_address_id'             => ['nullable', 'integer'],
            'billing_address_id'              => ['nullable', 'integer'],
            'shipping_address'                => ['nullable', 'array'],
            'shipping_address.recipient_name' => ['nullable', 'string', 'max:120'],
            'shipping_address.phone'          => ['nullable', 'string', 'max:40'],
            'shipping_address.country'        => ['required_with:shipping_address', 'string', 'size:2'],
            'shipping_address.state'          => ['nullable', 'string', 'max:120'],
            'shipping_address.city'           => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.area'           => ['nullable', 'string', 'max:120'],
            'shipping_address.block'          => ['nullable', 'string', 'max:60'],
            'shipping_address.street'         => ['nullable', 'string', 'max:120'],
            'shipping_address.building'       => ['nullable', 'string', 'max:60'],
            'shipping_address.floor'          => ['nullable', 'string', 'max:30'],
            'shipping_address.apartment'      => ['nullable', 'string', 'max:30'],
            'shipping_address.postal_code'    => ['nullable', 'string', 'max:30'],
            'payment_method_slug'             => ['required', 'string'],
            'shipping_minor'                  => ['nullable', 'integer', 'min:0'],
            'shipping_method_id'              => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'customer_notes'                  => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order = $this->checkout->place($request->user(), $data);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $method = PaymentMethod::where('slug', $data['payment_method_slug'])->firstOrFail();

        try {
            $result = $this->payments->initiateFor($order, $method);
        } catch (\Throwable $e) {
            // Order placed but payment couldn't initiate — surface it on the
            // order detail page so the customer can retry.
            return redirect("/orders/{$order->id}")
                ->with('error', 'Payment could not be initiated: ' . $e->getMessage());
        }

        if ($result->requiresAction && $result->redirectUrl) {
            return redirect()->away($result->redirectUrl);
        }

        if (! $result->succeeded) {
            return redirect("/orders/{$order->id}")
                ->with('error', 'Payment failed: ' . ($result->errorMessage ?? 'unknown error'));
        }

        return redirect("/orders/{$order->id}/confirm")
            ->with('success', "Thanks! Your order #{$order->number} has been placed.");
    }
}
