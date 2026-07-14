<?php

declare(strict_types=1);

namespace App\Services\Personalization;

use App\Models\CustomerAffinity;
use App\Models\CustomerProductView;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRecentSearch;
use App\Models\Wishlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.3 §14-16 — customer affinity scoring.
 *
 * SCORING FORMULA (deterministic, dev §14):
 *   For each qualifying signal:
 *     contribution = weight × recency_multiplier
 *   Per-(user, product) view contributions capped at
 *     config.affinity_caps.views_per_product = 5
 *   Sum contributions grouped by dimension (category / vendor / price_band).
 *
 * WEIGHTS from config.affinity_weights (dev §15 "do not hardcode weights").
 * DECAY buckets from config.recency_decay.
 *
 * PRIVACY: only reads the target user's own data. NEVER cross-references
 * other users. Reads customer_product_views (own rows), orders (own rows),
 * user_recent_searches (own rows), wishlists (own rows).
 */
class CustomerAffinityService
{
    /**
     * Full rebuild of one user's affinity rows. Idempotent — replaces
     * existing rows with the new snapshot. Called by
     * `personalization:rebuild [--user=]` and by post-purchase event handlers.
     */
    public function rebuildForUser(User $user): array
    {
        $weights = (array) config('marketplace_personalization.affinity_weights', []);
        $decay   = (array) config('marketplace_personalization.recency_decay', []);
        $caps    = (array) config('marketplace_personalization.affinity_caps', []);
        $now     = now();
        $window  = (int) config('marketplace_personalization.retention.customer_views_days', 90);

        // Aggregators keyed by "dim:id" and "dim:key"
        $catScores    = [];  // category_id => score
        $vendorScores = [];  // vendor_id   => score
        $bandScores   = [];  // band_key    => score
        $signalCounts = ['category' => [], 'vendor' => [], 'price_band' => []];
        $lastSignal   = ['category' => [], 'vendor' => [], 'price_band' => []];

        $addSignal = function (string $dim, $key, int $contribution, Carbon $at) use (
            &$catScores, &$vendorScores, &$bandScores, &$signalCounts, &$lastSignal
        ) {
            // v12.2.1 parse-error fix: PHP does not allow references inside
            // `match` expression arms (`'x' => &$var` is only valid in array
            // literals). PHP 8.5's tokenizer rejects the previous form with
            // "unexpected token '&'". Behavior is preserved: each branch
            // updates its own captured-by-reference accumulator directly,
            // and we short-circuit on unknown dimensions.
            if ($dim === 'category') {
                $catScores[$key]    = ($catScores[$key]    ?? 0) + $contribution;
            } elseif ($dim === 'vendor') {
                $vendorScores[$key] = ($vendorScores[$key] ?? 0) + $contribution;
            } elseif ($dim === 'price_band') {
                $bandScores[$key]   = ($bandScores[$key]   ?? 0) + $contribution;
            } else {
                return;
            }
            $signalCounts[$dim][$key] = ($signalCounts[$dim][$key] ?? 0) + 1;
            $prev = $lastSignal[$dim][$key] ?? null;
            if (! $prev || $at->gt($prev)) {
                $lastSignal[$dim][$key] = $at;
            }
        };

        // ── 1. Product views (capped per product) ─────────────────────
        $viewsPerProduct = max(1, (int) ($caps['views_per_product'] ?? 5));
        $views = CustomerProductView::query()
            ->where('user_id', $user->id)
            ->where('viewed_at', '>=', $now->copy()->subDays($window))
            ->with('product:id,category_id,vendor_id,price_minor')
            ->orderBy('viewed_at')
            ->get();
        $viewCountByProduct = [];
        foreach ($views as $view) {
            $pid = $view->product_id;
            $viewCountByProduct[$pid] = ($viewCountByProduct[$pid] ?? 0) + 1;
            if ($viewCountByProduct[$pid] > $viewsPerProduct) continue;
            if (! $view->product) continue;
            $mult = $this->recencyMultiplier($decay, $view->viewed_at);
            $contribution = (int) round(($weights['product_view'] ?? 3) * $mult);
            if ($contribution === 0) continue;
            $addSignal('category', $view->product->category_id, $contribution, $view->viewed_at);
            $addSignal('vendor',   $view->product->vendor_id,   $contribution, $view->viewed_at);
            $band = $this->priceBandKey((int) $view->product->price_minor);
            if ($band) $addSignal('price_band', $band, $contribution, $view->viewed_at);
        }

        // ── 2. Cart adds (via recommendation_events add_to_cart) ─────
        // Skipped in the initial pass to keep rebuild bounded; the
        // add_to_cart event is captured directly by
        // RecentlyViewedService::record when called from CartController.

        // ── 3. Wishlist adds ─────────────────────────────────────────
        $wishlists = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $now->copy()->subDays($window))
            ->with('product:id,category_id,vendor_id,price_minor')
            ->get();
        foreach ($wishlists as $w) {
            if (! $w->product) continue;
            $mult = $this->recencyMultiplier($decay, $w->created_at);
            $contribution = (int) round(($weights['wishlist_add'] ?? 12) * $mult);
            if ($contribution === 0) continue;
            $addSignal('category', $w->product->category_id, $contribution, $w->created_at);
            $addSignal('vendor',   $w->product->vendor_id,   $contribution, $w->created_at);
            $band = $this->priceBandKey((int) $w->product->price_minor);
            if ($band) $addSignal('price_band', $band, $contribution, $w->created_at);
        }

        // ── 4. Completed purchases (strongest signal per dev §14) ─────
        $qualifyingStatuses = [
            Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
        ];
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $qualifyingStatuses)
            ->where('created_at', '>=', $now->copy()->subDays($window))
            ->with(['items' => fn ($q) => $q->select('id', 'order_id', 'product_id')])
            ->get();
        $productCountByUser = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $productCountByUser[$item->product_id] = ($productCountByUser[$item->product_id] ?? 0) + 1;
            }
        }
        if (! empty($productCountByUser)) {
            $products = Product::whereIn('id', array_keys($productCountByUser))
                ->get(['id', 'category_id', 'vendor_id', 'price_minor']);
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $product = $products->firstWhere('id', $item->product_id);
                    if (! $product) continue;
                    $isRepeat = ($productCountByUser[$item->product_id] > 1);
                    $baseWeight = $isRepeat
                        ? ($weights['repeat_purchase'] ?? 60)
                        : ($weights['completed_purchase'] ?? 50);
                    $mult = $this->recencyMultiplier($decay, $order->created_at);
                    $contribution = (int) round($baseWeight * $mult);
                    if ($contribution === 0) continue;
                    $addSignal('category', $product->category_id, $contribution, $order->created_at);
                    $addSignal('vendor',   $product->vendor_id,   $contribution, $order->created_at);
                    $band = $this->priceBandKey((int) $product->price_minor);
                    if ($band) $addSignal('price_band', $band, $contribution, $order->created_at);
                }
            }
        }

        // ── 5. Recent searches (category proxy via search term) ──────
        // We don't have direct category resolution from search terms without
        // an NLP step, so we only bump the *most recent search terms*
        // themselves via a "search_term" dimension. Not stored in
        // customer_affinities (that's category/vendor/price_band only).
        // Instead the CustomerAffinityService::topCategories consumer can
        // fold in recent-search signals directly if needed.

        // ── 6. Persist ────────────────────────────────────────────────
        // Transaction: delete old rows for this user, insert new. Atomic.
        DB::transaction(function () use ($user, $catScores, $vendorScores, $bandScores, $signalCounts, $lastSignal, $now) {
            CustomerAffinity::where('user_id', $user->id)->delete();

            $rows = [];
            foreach ($catScores as $cid => $score) {
                if ($score <= 0) continue;
                $rows[] = [
                    'user_id'        => $user->id,
                    'dimension'      => CustomerAffinity::DIM_CATEGORY,
                    'dimension_id'   => $cid,
                    'dimension_key'  => null,
                    'score'          => (int) $score,
                    'signal_count'   => (int) ($signalCounts['category'][$cid] ?? 0),
                    'last_signal_at' => $lastSignal['category'][$cid] ?? $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            foreach ($vendorScores as $vid => $score) {
                if ($score <= 0) continue;
                $rows[] = [
                    'user_id'        => $user->id,
                    'dimension'      => CustomerAffinity::DIM_VENDOR,
                    'dimension_id'   => $vid,
                    'dimension_key'  => null,
                    'score'          => (int) $score,
                    'signal_count'   => (int) ($signalCounts['vendor'][$vid] ?? 0),
                    'last_signal_at' => $lastSignal['vendor'][$vid] ?? $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            foreach ($bandScores as $key => $score) {
                if ($score <= 0) continue;
                $rows[] = [
                    'user_id'        => $user->id,
                    'dimension'      => CustomerAffinity::DIM_PRICE_BAND,
                    'dimension_id'   => null,
                    'dimension_key'  => $key,
                    'score'          => (int) $score,
                    'signal_count'   => (int) ($signalCounts['price_band'][$key] ?? 0),
                    'last_signal_at' => $lastSignal['price_band'][$key] ?? $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            if (! empty($rows)) {
                CustomerAffinity::insert($rows);
            }
        });

        return [
            'categories' => count($catScores),
            'vendors'    => count($vendorScores),
            'bands'      => count($bandScores),
        ];
    }

    /**
     * Top-N category ids for this user, highest-affinity first.
     *
     * @return array<int>
     */
    public function topCategories(User $user, int $limit = 5): array
    {
        return CustomerAffinity::query()
            ->where('user_id', $user->id)
            ->where('dimension', CustomerAffinity::DIM_CATEGORY)
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('dimension_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<int>
     */
    public function topVendors(User $user, int $limit = 5): array
    {
        return CustomerAffinity::query()
            ->where('user_id', $user->id)
            ->where('dimension', CustomerAffinity::DIM_VENDOR)
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('dimension_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string>  band keys
     */
    public function preferredPriceBands(User $user, int $limit = 3): array
    {
        return CustomerAffinity::query()
            ->where('user_id', $user->id)
            ->where('dimension', CustomerAffinity::DIM_PRICE_BAND)
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('dimension_key')
            ->all();
    }

    /**
     * Recency multiplier for a timestamp based on config buckets.
     */
    public function recencyMultiplier(array $decay, ?Carbon $at): float
    {
        if (! $at) return 0.0;
        $days = $at->diffInDays(now());
        return match (true) {
            $days <= 7   => (float) ($decay['last_7_days']    ?? 1.0),
            $days <= 30  => (float) ($decay['days_8_to_30']   ?? 0.6),
            $days <= 90  => (float) ($decay['days_31_to_90']  ?? 0.3),
            default      => (float) ($decay['older_than_90']  ?? 0.0),
        };
    }

    /**
     * Which price band does a minor-unit price fall into?
     */
    public function priceBandKey(int $priceMinor): ?string
    {
        $bands = (array) config('marketplace_personalization.price_bands', []);
        foreach ($bands as $key => $range) {
            if ($priceMinor >= $range['min'] && $priceMinor <= $range['max']) {
                return $key;
            }
        }
        return null;
    }
}
