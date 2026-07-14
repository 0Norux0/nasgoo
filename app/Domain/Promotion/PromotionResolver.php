<?php
declare(strict_types=1);
namespace App\Domain\Promotion;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use Illuminate\Support\Collection;

/**
 * Phase 9 — given a Product, return the best applicable promotion.
 *
 * Order of precedence (most specific wins):
 *   1. product_specific  — targets this exact product
 *   2. category          — targets one of this product's categories
 *   3. vendor            — targets this product's vendor
 *   4. platform-wide     — no targets at all (rare; usually flash sale)
 *
 * Returns null when no usable promotion applies.
 */
class PromotionResolver
{
    /**
     * Get the best applicable promotion for a given product.
     */
    public static function bestForProduct(Product $product): ?Promotion
    {
        $usable = Promotion::usable()->with('targets')->get();

        $byScore = $usable->map(function (Promotion $p) use ($product) {
            $score = self::scoreForProduct($p, $product);
            return $score === null ? null : ['p' => $p, 'score' => $score];
        })->filter()->sortByDesc('score');

        $best = $byScore->first();
        return $best ? $best['p'] : null;
    }

    /**
     * Return [Promotion => discount_minor] for every cart line.
     * Useful for cart total computation.
     *
     * @return array<int, array{product_id:int, promotion_id:int|null, discount_minor:int}>
     */
    public static function forCart(Cart $cart): array
    {
        $result = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) continue;
            $p = self::bestForProduct($product);
            $lineSubtotal = (int) $item->unit_price_minor * (int) $item->quantity;
            $result[] = [
                'product_id' => $product->id,
                'promotion_id' => $p?->id,
                'discount_minor' => $p ? $p->computeDiscountMinor($lineSubtotal) : 0,
            ];
        }
        return $result;
    }

    /**
     * Returns null = not applicable. Otherwise an integer score
     * (higher = more specific).
     */
    private static function scoreForProduct(Promotion $p, Product $product): ?int
    {
        // No targets = platform-wide
        if ($p->targets->isEmpty()) return 1;

        foreach ($p->targets as $t) {
            if ($t->targetable_type === Product::class && $t->targetable_id === $product->id) {
                return 100;   // most specific
            }
            if ($t->targetable_type === \App\Models\Vendor::class && $t->targetable_id === $product->vendor_id) {
                return 50;
            }
            // Category targeting — left to a later phase if needed
        }
        return null;
    }
}
