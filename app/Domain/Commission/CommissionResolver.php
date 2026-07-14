<?php

declare(strict_types=1);

namespace App\Domain\Commission;

use App\Domain\Money\Money;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;

/**
 * Resolves which commission rule applies for a given (vendor, context) tuple.
 *
 * Resolution priority — most specific wins, ties broken by `priority` column
 * (lower priority value wins):
 *   1. product       — scope=product, scope_id=product_id          (Phase 3+)
 *   2. category      — scope=category, scope_id=category_id        (Phase 3+)
 *   3. vendor        — scope=vendor, scope_id=vendor_id            (Phase 2)
 *   4. package       — scope=package, scope_id=vendor_package_id   (Phase 2)
 *   5. global        — scope=global                                (Phase 2)
 *
 * Phase 3 completes the product + category resolution paths that
 * Phase 2 left as a stub.
 *
 * Each candidate must also match the optional product_type / payment_method
 * filters or be set to 'any' for those columns.
 */
final class CommissionResolver
{
    /** Specificity order — rules earlier in this list win when they're effective. */
    private const SCOPE_ORDER = [
        VendorCommissionRule::SCOPE_PRODUCT,
        VendorCommissionRule::SCOPE_CATEGORY,
        VendorCommissionRule::SCOPE_VENDOR,
        VendorCommissionRule::SCOPE_PACKAGE,
        VendorCommissionRule::SCOPE_GLOBAL,
    ];

    /**
     * @param array<string, mixed> $context  May contain:
     *   - product_type   (any|simple|variable|...)
     *   - payment_method (any|online|cod|wallet)
     *   - product_id     (Phase 3+) for product-scope resolution
     *   - category_id    (Phase 3+) for category-scope resolution
     *   - package_id     for package-scope resolution
     */
    public function resolve(Vendor $vendor, array $context = [], ?\DateTimeInterface $when = null): ?VendorCommissionRule
    {
        $when    ??= now();
        $product = $context['product_type'] ?? 'any';
        $payment = $context['payment_method'] ?? 'any';

        // Pull every candidate rule that *could* match this context, then
        // apply specificity ordering in PHP. Doing it in SQL would need a
        // CASE/ORDER FIELD expression that's portable across drivers — the
        // candidate set is tiny so PHP-side ordering is safer and faster.
        $candidates = VendorCommissionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($vendor, $context) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhere('scope', VendorCommissionRule::SCOPE_GLOBAL);

                if (isset($context['category_id'])) {
                    $q->orWhere(function ($q) use ($context) {
                        $q->where('scope', VendorCommissionRule::SCOPE_CATEGORY)
                          ->where('scope_id', $context['category_id']);
                    });
                }
                if (isset($context['product_id'])) {
                    $q->orWhere(function ($q) use ($context) {
                        $q->where('scope', VendorCommissionRule::SCOPE_PRODUCT)
                          ->where('scope_id', $context['product_id']);
                    });
                }
                if (isset($context['package_id'])) {
                    $q->orWhere(function ($q) use ($context) {
                        $q->where('scope', VendorCommissionRule::SCOPE_PACKAGE)
                          ->where('scope_id', $context['package_id']);
                    });
                }
            })
            ->where(function ($q) use ($product) {
                $q->where('product_type', 'any')->orWhere('product_type', $product);
            })
            ->where(function ($q) use ($payment) {
                $q->where('payment_method', 'any')->orWhere('payment_method', $payment);
            })
            ->get();

        // Drop anything outside effective window
        $candidates = $candidates->filter(fn ($r) => $r->isEffectiveAt($when));

        if ($candidates->isEmpty()) {
            return null;
        }

        // Sort by specificity (scope), then priority (low wins), then most-recent id
        $scopeOrderMap = array_flip(self::SCOPE_ORDER);

        $sorted = $candidates->sort(function (VendorCommissionRule $a, VendorCommissionRule $b) use ($scopeOrderMap) {
            $aRank = $scopeOrderMap[$a->scope] ?? PHP_INT_MAX;
            $bRank = $scopeOrderMap[$b->scope] ?? PHP_INT_MAX;
            if ($aRank !== $bRank) return $aRank <=> $bRank;
            if ($a->priority !== $b->priority) return $a->priority <=> $b->priority;
            return $b->id <=> $a->id;
        });

        return $sorted->first();
    }

    /**
     * Compute commission for the given base amount.
     * Returns null if no rule applies.
     */
    public function compute(Vendor $vendor, Money $base, array $context = []): ?Money
    {
        $rule = $this->resolve($vendor, $context);
        if (! $rule) {
            return null;
        }

        $commissionMinor = match ($rule->commission_type) {
            VendorCommissionRule::TYPE_PERCENT =>
                (int) round(($base->amount * (float) $rule->percent_value) / 100),
            VendorCommissionRule::TYPE_FIXED =>
                (int) ($rule->fixed_value_minor ?? 0),
            VendorCommissionRule::TYPE_FIXED_PLUS_PERCENT =>
                (int) round(($base->amount * (float) $rule->percent_value) / 100)
                    + (int) ($rule->fixed_value_minor ?? 0),
            default => 0,
        };

        return new Money($commissionMinor, $base->currency);
    }

    /**
     * Convenience wrapper for the most common call site (Phase 3+):
     * "what commission applies for THIS product?"
     */
    public function forProduct(Product $product, ?string $paymentMethod = null): ?VendorCommissionRule
    {
        // v5.8 — defensive eager-load. Callers SHOULD eager-load
        // product.vendor.activeSubscription.package + product.category before
        // calling this, but if any caller (now or in future) hands us a
        // freshly-fetched Product, the strict-mode lazy-load detector would
        // throw on the $product->vendor access two lines down. loadMissing is
        // a no-op when relations are already loaded, so this is free
        // insurance.
        $product->loadMissing([
            'vendor.activeSubscription.package',
            'category',
        ]);

        return $this->resolve($product->vendor, [
            'product_id'     => $product->id,
            'category_id'    => $product->category_id,
            'package_id'     => $product->vendor?->currentPackage()?->id,
            'product_type'   => $product->type,
            'payment_method' => $paymentMethod ?? 'any',
        ]);
    }
}
