<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): Response
    {
        $cart = $this->carts->getOrCreate($request->user());

        // v5.7 — eager-load every relation the cart presenter touches, otherwise
        // multi-item carts crash under Model::shouldBeStrict (the N+1 detector
        // fires on the second item where lazy-load would happen).
        $cart->load([
            'items.product.primaryImage',
            'items.vendor',
            'items.variant',
        ]);
        $cart->load(['items.product:id,slug,name,vendor_id', 'items.product.primaryImage:id,product_id,path',
                     'items.variant:id,name,attribute_values', 'items.vendor:id,business_name,slug',
                     // Phase 7 — needed for the cart customization summary block
                     'items.customizations']);

        return Inertia::render('Cart/Show', [
            'cart' => $this->present($cart, $request->user()),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity'   => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::find($data['product_id']);
        try {
            $this->carts->addItem(
                $request->user(),
                $product,
                $data['quantity'] ?? 1,
                $data['variant_id'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Added '{$product->name}' to your cart.");
    }

    /**
     * Phase 11B.2 §10+§20+§33 — batch add for Frequently Bought Together.
     *
     * Accepts an array of product IDs (each with optional quantity + variant)
     * and adds them one-by-one through the existing CartService. EVERY id is
     * re-validated server-side regardless of what the frontend submitted, per
     * dev §33:
     *
     *   "Server-side validation must recheck every product before adding it
     *    to cart. Never trust recommendation IDs submitted by the frontend."
     *
     * Multi-vendor handling: the underlying CartService already routes items
     * to the correct vendor sub-cart (Phase 4); the multi-vendor checkout
     * split (Phase 9) is unchanged. We do NOT create a parallel cart
     * mechanism per dev §20.
     *
     * Variant gate: products of type='variable' that require variant
     * selection are REJECTED here with a clear message — the customer must
     * pick a variant via the regular product page first. This implements
     * dev §19: "Frequently Bought Together should not bypass required
     * variant selection."
     */
    public function addBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'items.*.variant_id' => ['nullable', 'integer'],
        ]);

        $added   = 0;
        $skipped = [];

        foreach ($data['items'] as $row) {
            $product = Product::with('vendor')->find($row['product_id']);

            // Server-side eligibility re-check (dev §33).
            if (! $product || $product->status !== Product::STATUS_PUBLISHED) {
                $skipped[] = "Product #{$row['product_id']} unavailable";
                continue;
            }
            if (! $product->vendor || $product->vendor->status !== \App\Models\Vendor::STATUS_APPROVED) {
                $skipped[] = "Product '{$product->name}' vendor suspended";
                continue;
            }
            if ($product->track_stock && (int) $product->stock <= 0) {
                $skipped[] = "Product '{$product->name}' out of stock";
                continue;
            }
            // Variant gate (dev §19)
            if ($product->type === Product::TYPE_VARIABLE && empty($row['variant_id'])) {
                $skipped[] = "Product '{$product->name}' requires variant selection";
                continue;
            }

            try {
                $this->carts->addItem(
                    $request->user(),
                    $product,
                    (int) ($row['quantity'] ?? 1),
                    $row['variant_id'] ?? null,
                );
                $added++;
            } catch (\Throwable $e) {
                $skipped[] = $e->getMessage();
            }
        }

        $msg = "Added $added item(s) to your cart.";
        if (! empty($skipped)) {
            $msg .= ' Skipped: ' . implode('; ', array_slice($skipped, 0, 3));
        }
        return back()->with($added > 0 ? 'success' : 'error', $msg);
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $this->carts->updateQuantity($request->user(), $item, $data['quantity']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        $this->carts->removeItem($request->user(), $item);
        return back()->with('success', 'Item removed.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->carts->clear($request->user());
        return redirect('/cart')->with('success', 'Cart cleared.');
    }

    /** Shape the cart for Inertia. */
    private function present(Cart $cart, ?\App\Models\User $user = null): array
    {
        // Phase 9 v9.1 — eager-load coupon so present() can include code+discount
        // without an extra query per cart render.
        if ($cart->coupon_id && ! $cart->relationLoaded('coupon')) {
            $cart->load('coupon:id,code,discount_type,discount_value');
        }

        // Phase 10 v10.8 — canonical promotion-aware breakdown. PricingService
        // computes per-line promotion discount first, then re-validates the
        // coupon against the post-promotion subtotal (dev §7 stacking).
        $pricing  = app(\App\Domain\Pricing\PricingService::class);
        $breakdown = $pricing->priceForCart($cart, $user);

        // Index the lines by cart_item_id so we can merge per-line promotion
        // fields into the existing item shape without changing the order.
        $lineById = [];
        foreach ($breakdown['lines'] as $ln) {
            $lineById[$ln['cart_item_id']] = $ln;
        }

        return [
            'id'              => $cart->id,
            'currency'        => $cart->currency,
            'items_count'     => $cart->items_count,
            'subtotal'        => $breakdown['subtotal'],
            'subtotal_minor'  => $breakdown['subtotal_minor'],
            // Phase 10 v10.8 — promotion total (sum of per-line promotion discounts)
            'promotion'       => $breakdown['promotion_total_minor'] > 0 ? [
                'discount'       => $breakdown['promotion_total'],
                'discount_minor' => $breakdown['promotion_total_minor'],
            ] : null,
            'subtotal_after_promotion'       => $breakdown['subtotal_after_promotion'],
            'subtotal_after_promotion_minor' => $breakdown['subtotal_after_promotion_minor'],
            // Phase 9 v9.1 — coupon snapshot for the cart UI. NOTE the coupon
            // discount is now computed against subtotal_after_promotion per
            // v10.8 stacking; cart.coupon.discount may differ from what the
            // raw $cart->discount_minor column shows (which is no longer the
            // source of truth — present() uses the PricingService re-derived
            // value).
            'coupon'          => $breakdown['coupon_id'] ? [
                'id'             => $breakdown['coupon_id'],
                'code'           => $breakdown['coupon_code'],
                'discount'       => $breakdown['coupon_discount'],
                'discount_minor' => $breakdown['coupon_discount_minor'],
            ] : null,
            'payable'         => $breakdown['payable'],
            'payable_minor'   => $breakdown['payable_minor'],
            'items'           => $cart->items->map(function ($i) use ($lineById) {
                $line = $lineById[$i->id] ?? null;
                return [
                    'id'              => $i->id,
                    'product_id'      => $i->product_id,
                    'product_slug'    => $i->product?->slug,
                    'product_name'    => $i->product?->name,
                    'product_thumb'   => $i->product?->primaryImage?->url,
                    'variant_id'      => $i->variant_id,
                    'variant_name'    => $i->variant?->name,
                    'variant_attrs'   => $i->variant?->attribute_values,
                    'vendor_name'     => $i->vendor?->business_name,
                    'vendor_slug'     => $i->vendor?->slug,
                    'quantity'        => $i->quantity,
                    'unit_price'      => number_format($i->unit_price_minor / 100, 2),
                    'line_total'      => number_format($i->lineTotalMinor() / 100, 2),
                    // Phase 10 v10.8 — per-line promotion-aware fields. unit_price_final
                    // equals unit_price when no promotion applies; otherwise it's
                    // the discounted unit price. promotion is null when no
                    // promotion applies.
                    'unit_price_final' => $line['unit_final'] ?? number_format($i->unit_price_minor / 100, 2),
                    'line_total_final' => $line['line_final'] ?? number_format($i->lineTotalMinor() / 100, 2),
                    'line_promotion'   => $line['line_promotion'] ?? null,
                    'promotion'        => $line['promotion'] ?? null,
                    // Phase 7 — customization summary + per-row fee
                    'customization_fee' => $i->customization_fee_minor > 0
                        ? number_format($i->customization_fee_minor / 100, 2)
                        : null,
                    'customizations'  => $i->customizations->map(fn ($c) => [
                        'id'                 => $c->id,
                        'field_key'          => $c->field_key,
                        'field_label'        => $c->field_label,
                        'field_type'         => $c->field_type,
                        'value'              => $c->value,
                        'file_original_name' => $c->file_original_name,
                        'extra_fee'          => $c->extra_fee_minor > 0
                            ? number_format($c->extra_fee_minor / 100, 2)
                            : null,
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
