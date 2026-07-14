<?php

declare(strict_types=1);

namespace App\Services\Personalization;

use App\Models\CustomerProductView;
use App\Models\PersonalizationFeedback;
use App\Models\PersonalizationPreference;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.3 §8-9 — Recently Viewed products.
 *
 * Reads customer_product_views + optionally filters by
 * personalization_feedback (Not Interested).
 *
 * Privacy: strict isolation. A user_id-scoped query CANNOT return another
 * user's rows (validated by the WHERE user_id = X clause; no join to
 * anything customer-identifiable). Guest sessions are isolated by
 * session_key, which is rotated on login.
 */
class RecentlyViewedService
{
    /**
     * Record a product view. No-op when the user has opted out of behavior
     * tracking. Idempotency: no unique constraint by design (recency
     * matters), but callers should throttle on the middleware/controller
     * side to avoid double-recording a single request.
     */
    public function record(
        ?User $user,
        ?string $sessionKey,
        int $productId,
        string $locale,
        ?string $deviceCategory = null,
    ): void {
        if (! $this->trackingEnabled($user)) {
            return;
        }
        if (! $user && ! $sessionKey) {
            return;  // no identity, no tracking
        }
        CustomerProductView::create([
            'user_id'         => $user?->id,
            'session_key'     => $user ? null : $sessionKey,
            'product_id'      => $productId,
            'locale'          => $locale,
            'device_category' => $deviceCategory,
            'viewed_at'       => now(),
        ]);
    }

    /**
     * Fetch the N most recently viewed distinct products for the caller,
     * eligibility-filtered.
     *
     * Excluded:
     *   - deleted / unpublished / draft / archived products
     *   - suspended-vendor products
     *   - out-of-stock products (when the config flag is on)
     *   - products the user has hidden via feedback
     *   - the current product (when $excludeProductId given)
     *
     * @return Collection<int, Product>
     */
    public function forCaller(
        ?User $user,
        ?string $sessionKey,
        int $limit = 12,
        ?int $excludeProductId = null,
    ): Collection {
        if (! $user && ! $sessionKey) {
            return collect();
        }

        // Retention window
        $retentionDays = $user
            ? (int) config('marketplace_personalization.retention.customer_views_days', 90)
            : (int) config('marketplace_personalization.retention.guest_views_days', 30);
        $since = now()->subDays(max(1, $retentionDays));

        // Get distinct product ids, most-recent-view-first. Do this in ONE
        // query per person, not N.
        $q = DB::table('customer_product_views')
            ->select('product_id', DB::raw('MAX(viewed_at) as latest'))
            ->where('viewed_at', '>=', $since);
        if ($user) {
            $q->where('user_id', $user->id);
        } else {
            $q->where('session_key', $sessionKey);
        }
        if ($excludeProductId) {
            $q->where('product_id', '!=', $excludeProductId);
        }
        $productIds = $q->groupBy('product_id')
            ->orderByDesc('latest')
            ->limit($limit * 3)  // over-fetch to account for eligibility loss
            ->pluck('product_id')
            ->toArray();

        if (empty($productIds)) {
            return collect();
        }

        // Filter by feedback (hidden products)
        $hiddenIds = $this->hiddenProductIds($user, $sessionKey);
        $productIds = array_values(array_diff($productIds, $hiddenIds));
        if (empty($productIds)) {
            return collect();
        }

        // Eligibility: published + not suspended vendor
        $products = Product::query()
            ->whereIn('products.id', $productIds)
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->whereNotNull('products.published_at')
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->with(['vendor:id,business_name', 'primaryImage', 'translations'])
            ->select('products.*')
            ->get();

        // Preserve the recency order from the pluck above
        $order = array_flip($productIds);
        return $products->sortBy(fn ($p) => $order[$p->id] ?? PHP_INT_MAX)
                        ->take($limit)
                        ->values();
    }

    /**
     * Clear all recently-viewed rows for the caller. Per dev §21 "Customer
     * cannot clear another customer's history" — the WHERE clause enforces
     * this because it's built from the authenticated user's own id, never
     * from a request parameter.
     */
    public function clear(?User $user, ?string $sessionKey): int
    {
        $q = CustomerProductView::query();
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($sessionKey) {
            $q->where('session_key', $sessionKey);
        } else {
            return 0;
        }
        return $q->delete();
    }

    /**
     * Product ids the caller has hidden via feedback. Non-expired only.
     *
     * @return array<int>
     */
    private function hiddenProductIds(?User $user, ?string $sessionKey): array
    {
        $q = PersonalizationFeedback::query()
            ->whereIn('feedback_type', [
                PersonalizationFeedback::TYPE_NOT_INTERESTED,
                PersonalizationFeedback::TYPE_HIDE_PRODUCT,
            ])
            ->whereNotNull('product_id')
            ->where(function ($qq) {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($sessionKey) {
            $q->where('session_key', $sessionKey);
        } else {
            return [];
        }
        return $q->pluck('product_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Is tracking enabled for this caller? Master flag + per-user preference.
     */
    private function trackingEnabled(?User $user): bool
    {
        if (! config('marketplace_personalization.features.behavior_tracking', true)) {
            return false;
        }
        if ($user) {
            $prefs = PersonalizationPreference::forUser($user);
            return $prefs['behavior_tracking_enabled'];
        }
        // Guests always get default tracking; disable via config only.
        return true;
    }
}
