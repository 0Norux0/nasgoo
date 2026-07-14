<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Promotion\CouponValidator;
use App\Domain\Promotion\PromotionResolver;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 10 v10.8 — canonical promotion-aware pricing service.
 *
 * Single source of truth for promotion eligibility + final-price math.
 * Used by:
 *   - CatalogController (product listing + detail)
 *   - HomeController (featured cards)
 *   - CartController (cart presentation)
 *   - CheckoutController + CheckoutService (order placement)
 *
 * Pre-v10.8 the Phase 9 PromotionResolver existed but was wired into
 * exactly nothing — the Deals page was the only caller. Product listings,
 * cart, and checkout all ignored promotions, producing the dev's bug:
 * "Deals page says 20% off all products" while every other surface
 * showed the un-discounted price.
 *
 * Stacking rule (dev §7):
 *   1. Compute per-line promotion discount   → promotion_total
 *   2. subtotal_after_promotion = subtotal − promotion_total
 *   3. Coupon applies to subtotal_after_promotion
 *   4. final = subtotal_after_promotion − coupon_discount (+ shipping + tax)
 *
 * All arithmetic in integer minor units. Rounding is floor-based per line
 * (matches Promotion::computeDiscountMinor) — deterministic across runs.
 * No React-side discount math. Reads only.
 */
final class PricingService
{
    /**
     * Price one product including any applicable promotion.
     *
     * @return array{
     *   original_minor: int,
     *   promotion: ?array{id:int,title:string,type:string,discount_type:string,discount_value:string,badge:string},
     *   discount_minor: int,
     *   final_minor: int,
     *   original: string,
     *   discount: string,
     *   final: string,
     * }
     */
    public function priceForProduct(Product $product, ?Promotion $resolved = null): array
    {
        $original = (int) $product->price_minor;
        $promo = $resolved ?? PromotionResolver::bestForProduct($product);
        $discount = $promo ? $promo->computeDiscountMinor($original) : 0;
        $final = max(0, $original - $discount);

        return [
            'original_minor' => $original,
            'promotion'      => $promo ? $this->promotionDto($promo) : null,
            'discount_minor' => $discount,
            'final_minor'    => $final,
            'original'       => self::format($original),
            'discount'       => self::format($discount),
            'final'          => self::format($final),
        ];
    }

    /**
     * Phase 11B.2 v11B.2.2 §3 — full unit-level breakdown with quantity.
     *
     * Matches the dev's canonical API signature
     *   priceProduct(Product, int $quantity, ?User $customer, PricingContext $context)
     * thin-wrapping the existing priceForProduct so callers get the full
     * breakdown contract per dev §3 without duplicating math.
     *
     * The returned array contains BOTH the legacy keys (so existing callers
     * keep working) AND the new dev-§3 contract fields:
     *   base_unit_price_minor, effective_unit_price_minor, quantity,
     *   base_line_subtotal_minor, promotion_discount_minor, final_line_total_minor,
     *   applied_promotion_ids, currency, calculation_at, pricing_version.
     *
     * Customer eligibility (per dev §22 — customer_segment / first_order /
     * minimum_order) is checked at the Promotion level (the resolver already
     * filters by `usable()` scope); the $customer argument is reserved for
     * customer-segment rules in future phases.
     *
     * @return array<string, mixed>
     */
    public function priceProductWithQuantity(
        Product $product,
        int $quantity = 1,
        ?User $customer = null,
        array $context = []
    ): array {
        $quantity = max(1, $quantity);
        $unit = $this->priceForProduct($product);
        $baseUnitMinor      = (int) $unit['original_minor'];
        $effectiveUnitMinor = (int) $unit['final_minor'];
        $unitDiscountMinor  = $baseUnitMinor - $effectiveUnitMinor;

        return array_merge($unit, [
            'base_unit_price_minor'      => $baseUnitMinor,
            'effective_unit_price_minor' => $effectiveUnitMinor,
            'quantity'                   => $quantity,
            'base_line_subtotal_minor'   => $baseUnitMinor * $quantity,
            'promotion_discount_minor'   => $unitDiscountMinor * $quantity,
            'final_line_total_minor'     => $effectiveUnitMinor * $quantity,
            'applied_promotion_ids'      => $unit['promotion']
                                              ? [(int) $unit['promotion']['id']]
                                              : [],
            'currency'                   => $product->currency,
            'calculation_at'             => now()->toIso8601String(),
            // Pricing version: a stable hash over the inputs that determined
            // this price. Lets downstream code detect a "stale" calculation
            // without re-running the engine.
            'pricing_version'            => substr(hash('sha256',
                "p:{$product->id}|pm:{$baseUnitMinor}|d:{$unitDiscountMinor}|q:{$quantity}|c:" .
                ($customer?->id ?? 'guest')
            ), 0, 16),
            // Context echo (for audit), never used for math
            'context'                    => $context,
        ]);
    }

    /**
     * Bulk-price many products. Performs a SINGLE query to load all usable
     * promotions, then resolves each product in-memory — avoids N+1 on
     * listings. Returns an associative array keyed by product id.
     *
     * @param Collection<int, Product>|array<Product> $products
     * @return array<int, array<string, mixed>>
     */
    public function priceForProducts(Collection|array $products): array
    {
        $promos = Promotion::usable()->with('targets')->get();
        $out = [];
        foreach ($products as $p) {
            // Re-implement bestForProduct inline using the preloaded set,
            // so we don't re-query promotions per product.
            $best = $this->bestFromSet($p, $promos);
            $out[$p->id] = $this->priceForProduct($p, $best);
        }
        return $out;
    }

    /**
     * Compute the full cart breakdown with promotion + coupon stacking.
     *
     * @return array{
     *   currency:string,
     *   items_count:int,
     *   subtotal_minor:int,
     *   promotion_total_minor:int,
     *   subtotal_after_promotion_minor:int,
     *   coupon_id:?int,
     *   coupon_code:?string,
     *   coupon_discount_minor:int,
     *   payable_minor:int,
     *   subtotal:string,
     *   promotion_total:string,
     *   subtotal_after_promotion:string,
     *   coupon_discount:string,
     *   payable:string,
     *   lines: array<int, array<string, mixed>>,
     * }
     */
    public function priceForCart(Cart $cart, ?User $user = null): array
    {
        // Eager-load anything we need; idempotent
        $cart->loadMissing(['items.product', 'coupon']);

        $promos = Promotion::usable()->with('targets')->get();

        $subtotal = 0;
        $promoTotal = 0;
        $lines = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                // Defensive — shouldn't happen with FK constraints
                $lineTotal = $item->lineTotalMinor();
                $subtotal += $lineTotal;
                $lines[] = [
                    'cart_item_id'              => $item->id,
                    'product_id'                => $item->product_id,
                    'quantity'                  => (int) $item->quantity,
                    'unit_original_minor'       => (int) $item->unit_price_minor,
                    'line_original_minor'       => $lineTotal,
                    'promotion'                 => null,
                    'line_promotion_minor'      => 0,
                    'unit_final_minor'          => (int) $item->unit_price_minor,
                    'line_final_minor'          => $lineTotal,
                    'unit_original'             => self::format((int) $item->unit_price_minor),
                    'unit_final'                => self::format((int) $item->unit_price_minor),
                    'line_original'             => self::format($lineTotal),
                    'line_promotion'            => self::format(0),
                    'line_final'                => self::format($lineTotal),
                ];
                continue;
            }

            $best = $this->bestFromSet($product, $promos);
            $unitOriginal = (int) $item->unit_price_minor;
            $qty = (int) $item->quantity;
            $customizationFee = (int) ($item->customization_fee_minor ?? 0);
            // Line total (matches existing CartItem::lineTotalMinor)
            $lineOriginal = ($qty * $unitOriginal) + $customizationFee;

            // Promotion applies to the per-unit price × quantity portion only;
            // customization surcharges are NOT discounted (consistent with
            // PromotionResolver::forCart precedent + protects vendor margin
            // on custom work).
            $promoEligiblePortion = $qty * $unitOriginal;
            $linePromotion = $best ? $best->computeDiscountMinor($promoEligiblePortion) : 0;
            $lineFinal = max(0, $lineOriginal - $linePromotion);
            $unitFinal = $qty > 0 ? max(0, $unitOriginal - (int) floor($linePromotion / $qty)) : $unitOriginal;

            $subtotal   += $lineOriginal;
            $promoTotal += $linePromotion;

            $lines[] = [
                'cart_item_id'              => $item->id,
                'product_id'                => $product->id,
                'quantity'                  => $qty,
                'unit_original_minor'       => $unitOriginal,
                'line_original_minor'       => $lineOriginal,
                'promotion'                 => $best ? $this->promotionDto($best) : null,
                'line_promotion_minor'      => $linePromotion,
                'unit_final_minor'          => $unitFinal,
                'line_final_minor'          => $lineFinal,
                'unit_original'             => self::format($unitOriginal),
                'unit_final'                => self::format($unitFinal),
                'line_original'             => self::format($lineOriginal),
                'line_promotion'            => self::format($linePromotion),
                'line_final'                => self::format($lineFinal),
            ];
        }

        $subtotalAfterPromo = max(0, $subtotal - $promoTotal);

        // Re-validate coupon against the POST-promotion subtotal so stacking
        // is correct per dev §7. We DON'T trust $cart->discount_minor (could
        // be stale or attacker-mutated); re-run CouponValidator.
        $couponId = null;
        $couponCode = null;
        $couponDiscount = 0;
        if ($cart->coupon_id && $user !== null) {
            $code = (string) ($cart->coupon?->code ?? '');
            if ($code !== '') {
                $result = CouponValidator::validate($code, $cart, $user);
                if ($result['ok'] && $result['coupon']) {
                    // The validator computed the coupon discount against the
                    // gross subtotal. Recompute against the post-promotion
                    // subtotal so we honor stacking.
                    $couponId = $result['coupon']->id;
                    $couponCode = $result['coupon']->code;
                    $couponDiscount = (int) $result['coupon']->computeDiscountMinor($subtotalAfterPromo);
                }
            }
        }

        $payable = max(0, $subtotalAfterPromo - $couponDiscount);

        return [
            'currency'                       => $cart->currency,
            'items_count'                    => (int) $cart->items_count,
            'subtotal_minor'                 => $subtotal,
            'promotion_total_minor'          => $promoTotal,
            'subtotal_after_promotion_minor' => $subtotalAfterPromo,
            'coupon_id'                      => $couponId,
            'coupon_code'                    => $couponCode,
            'coupon_discount_minor'          => $couponDiscount,
            'payable_minor'                  => $payable,
            'subtotal'                       => self::format($subtotal),
            'promotion_total'                => self::format($promoTotal),
            'subtotal_after_promotion'       => self::format($subtotalAfterPromo),
            'coupon_discount'                => self::format($couponDiscount),
            'payable'                        => self::format($payable),
            'lines'                          => $lines,
        ];
    }

    /**
     * Find the best promotion for a product from a preloaded set.
     * Mirrors PromotionResolver's scoreForProduct logic but avoids
     * a per-product query.
     */
    private function bestFromSet(Product $product, Collection $promos): ?Promotion
    {
        $bestScore = -1;
        $bestPromo = null;
        foreach ($promos as $p) {
            $score = $this->scoreForProduct($p, $product);
            if ($score !== null && $score > $bestScore) {
                $bestScore = $score;
                $bestPromo = $p;
            }
        }
        return $bestPromo;
    }

    private function scoreForProduct(Promotion $p, Product $product): ?int
    {
        // No targets = platform-wide (the dev's "all products" case)
        if ($p->targets->isEmpty()) {
            return 1;
        }
        $productClass = \App\Models\Product::class;
        $vendorClass = \App\Models\Vendor::class;
        $categoryClass = \App\Models\Category::class;
        foreach ($p->targets as $t) {
            if ($t->targetable_type === $productClass && (int) $t->targetable_id === (int) $product->id) {
                return 100;
            }
            if ($t->targetable_type === $categoryClass && (int) $t->targetable_id === (int) $product->category_id) {
                return 60;
            }
            if ($t->targetable_type === $vendorClass && (int) $t->targetable_id === (int) $product->vendor_id) {
                return 50;
            }
        }
        return null;
    }

    /**
     * Promotion DTO shape used by every consumer (listings, cart, order).
     *
     * @return array{id:int,title:string,type:string,discount_type:string,discount_value:string,badge:string}
     */
    private function promotionDto(Promotion $p): array
    {
        return [
            'id'             => (int) $p->id,
            'title'          => (string) $p->title,
            'type'           => (string) $p->promotion_type,
            'discount_type'  => (string) $p->discount_type,
            'discount_value' => (string) $p->discount_value,
            'badge'          => $this->badgeFor($p),
        ];
    }

    private function badgeFor(Promotion $p): string
    {
        if ($p->discount_type === Promotion::DISCOUNT_PERCENTAGE) {
            // Drop trailing zeros for whole-number percentages: "20% OFF" not "20.00% OFF"
            $v = (float) $p->discount_value;
            $str = $v == (int) $v ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
            return "{$str}% OFF";
        }
        if ($p->discount_type === Promotion::DISCOUNT_FIXED) {
            $amount = self::format((int) $p->discount_value);
            return "{$amount} {$p->currency} OFF";
        }
        return 'PROMO';
    }

    /** Integer minor → "X.XX" string. */
    public static function format(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
