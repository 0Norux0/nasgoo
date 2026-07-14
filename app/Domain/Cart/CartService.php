<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cart operations — every public method is transactional and recomputes the
 * denormalised cart totals at the end. Phase 4 keeps the cart logged-in only;
 * guest cart + merge-on-login is a future polish item (see PHASE_4_REPORT).
 */
class CartService
{
    /**
     * Return the user's cart, creating it on first call. Idempotent.
     */
    public function getOrCreate(User $user, string $currency = 'KWD'): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => $currency, 'subtotal_minor' => 0, 'items_count' => 0],
        );
    }

    /**
     * Add (or increment qty if already in cart) a product line.
     * Enforces:
     *   - product must be published
     *   - variant (if given) must belong to that product
     *   - stock check when track_stock is on
     *   - cart currency must match product currency (no FX in Phase 4)
     */
    public function addItem(User $user, Product $product, int $quantity = 1, ?int $variantId = null, bool $forceNewLine = false): CartItem
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }
        if ($product->status !== Product::STATUS_PUBLISHED) {
            throw new RuntimeException('This product is not available for purchase.');
        }

        $variant = null;
        if ($variantId !== null) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('id', $variantId)
                ->where('is_active', true)
                ->first();
            if (! $variant) {
                throw new InvalidArgumentException('Selected variant is not available.');
            }
        }

        $unitPriceMinor = $variant?->price_minor ?? $product->price_minor;
        $currency       = $variant?->currency ?? $product->currency;

        if ($unitPriceMinor <= 0) {
            throw new RuntimeException('This product cannot be purchased at this time.');
        }

        return DB::transaction(function () use ($user, $product, $variant, $quantity, $unitPriceMinor, $currency, $forceNewLine) {
            $cart = $this->getOrCreate($user, $currency);

            // Phase 4 limitation: one currency per cart. Mixed-currency carts
            // are intentionally rejected until FX handling lands in Phase 5+.
            if ($cart->currency !== $currency) {
                throw new RuntimeException("Your cart is in {$cart->currency}; this product is priced in {$currency}.");
            }

            // Phase 7 — customizable products NEVER merge with existing lines.
            // Two customers' designs are not interchangeable; the caller (the
            // CustomizationCartService) passes forceNewLine=true to opt in.
            $existing = $forceNewLine ? null : $cart->items()
                ->where('product_id', $product->id)
                ->where('variant_id', $variant?->id)
                ->first();

            if ($existing) {
                $newQty = $existing->quantity + $quantity;
                $this->assertWithinStock($product, $variant, $newQty);
                $existing->update(['quantity' => $newQty]);
                $item = $existing;
            } else {
                $this->assertWithinStock($product, $variant, $quantity);
                $item = $cart->items()->create([
                    'product_id'       => $product->id,
                    'variant_id'       => $variant?->id,
                    'vendor_id'        => $product->vendor_id,
                    'quantity'         => $quantity,
                    'unit_price_minor' => $unitPriceMinor,
                    'currency'         => $currency,
                ]);
            }

            $this->recomputeTotals($cart);
            return $item->refresh();
        });
    }

    public function updateQuantity(User $user, int $itemId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Use removeItem() to delete a line.');
        }

        return DB::transaction(function () use ($user, $itemId, $quantity) {
            $cart = $this->getOrCreate($user);
            $item = $cart->items()->where('id', $itemId)->firstOrFail();

            $product = $item->product;
            $variant = $item->variant;
            $this->assertWithinStock($product, $variant, $quantity);

            $item->update(['quantity' => $quantity]);
            $this->recomputeTotals($cart);
            return $item->refresh();
        });
    }

    public function removeItem(User $user, int $itemId): void
    {
        DB::transaction(function () use ($user, $itemId) {
            $cart = $this->getOrCreate($user);
            $cart->items()->where('id', $itemId)->delete();
            $this->recomputeTotals($cart);
        });
    }

    public function clear(User $user): void
    {
        DB::transaction(function () use ($user) {
            $cart = $this->getOrCreate($user);
            $cart->items()->delete();
            $this->recomputeTotals($cart);
        });
    }

    /**
     * Reaggregate subtotal/items_count from cart_items. Cheap — call after
     * any mutation.
     */
    public function recomputeTotals(Cart $cart): void
    {
        $cart->loadMissing('items');
        $subtotal = 0;
        $count    = 0;
        foreach ($cart->items as $item) {
            $subtotal += $item->lineTotalMinor();
            $count    += $item->quantity;
        }
        $cart->update([
            'subtotal_minor' => $subtotal,
            'items_count'    => $count,
        ]);
    }

    private function assertWithinStock(Product $product, ?ProductVariant $variant, int $desiredQty): void
    {
        if ($variant) {
            if ($variant->stock < $desiredQty) {
                throw new RuntimeException("Only {$variant->stock} of this variant left in stock.");
            }
            return;
        }
        if ($product->track_stock && $product->stock < $desiredQty) {
            throw new RuntimeException("Only {$product->stock} in stock.");
        }
    }
}
