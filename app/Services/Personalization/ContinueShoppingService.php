<?php

declare(strict_types=1);

namespace App\Services\Personalization;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wishlist;
use Illuminate\Support\Collection;

/**
 * Phase 11B.3 §10 — Continue Shopping.
 *
 * Priority per dev §10:
 *   1. cart items not yet purchased
 *   2. wishlist items
 *   3. recently viewed products
 * Excludes:
 *   - completed-purchase products (unless configured as "consumable/repeat")
 *   - inactive/unpublished products
 *   - suspended-vendor products
 *
 * Guest support: cart items only, via session cart lookup. Wishlist requires
 * authentication.
 */
class ContinueShoppingService
{
    public function __construct(
        private RecentlyViewedService $recentlyViewed,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function forUser(?User $user, ?string $sessionKey, int $limit = 8): Collection
    {
        if (! $user && ! $sessionKey) {
            return collect();
        }

        $picked = [];
        $seen   = [];

        // 1. Cart items (highest priority)
        foreach ($this->cartCandidates($user, $sessionKey, $limit) as $p) {
            if (isset($seen[$p->id])) continue;
            $picked[] = $p;
            $seen[$p->id] = true;
            if (count($picked) >= $limit) return collect($picked);
        }

        // 2. Wishlist items (auth only)
        if ($user) {
            foreach ($this->wishlistCandidates($user, $limit) as $p) {
                if (isset($seen[$p->id])) continue;
                $picked[] = $p;
                $seen[$p->id] = true;
                if (count($picked) >= $limit) return collect($picked);
            }
        }

        // 3. Recently viewed (excluding already-purchased)
        $purchasedIds = $user ? $this->completedPurchaseProductIds($user) : [];
        foreach ($this->recentlyViewed->forCaller($user, $sessionKey, $limit * 2) as $p) {
            if (isset($seen[$p->id]) || in_array($p->id, $purchasedIds, true)) continue;
            $picked[] = $p;
            $seen[$p->id] = true;
            if (count($picked) >= $limit) return collect($picked);
        }

        return collect($picked);
    }

    /**
     * @return Collection<int, Product>
     */
    private function cartCandidates(?User $user, ?string $sessionKey, int $limit): Collection
    {
        $cart = null;
        if ($user) {
            $cart = Cart::query()->where('user_id', $user->id)->first();
        } elseif ($sessionKey) {
            $cart = Cart::query()->where('session_id', $sessionKey)->first();
        }
        if (! $cart) return collect();

        return Product::query()
            ->whereIn('products.id', $cart->items()->pluck('product_id'))
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->with(['vendor:id,business_name', 'primaryImage', 'translations'])
            ->select('products.*')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function wishlistCandidates(User $user, int $limit): Collection
    {
        $productIds = Wishlist::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit * 2)
            ->pluck('product_id');
        if ($productIds->isEmpty()) return collect();

        return Product::query()
            ->whereIn('products.id', $productIds)
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->with(['vendor:id,business_name', 'primaryImage', 'translations'])
            ->select('products.*')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int>
     */
    private function completedPurchaseProductIds(User $user): array
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
            ])
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->pluck('order_items.product_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();
    }
}
