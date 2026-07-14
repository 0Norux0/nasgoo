<?php

declare(strict_types=1);

/**
 * Phase 11B.2 — recommendation engine settings.
 *
 * Per dev §27 — "Use the existing settings architecture. Do not create
 * another competing configuration system." This file is a dedicated
 * config namespace under the same Laravel config/* directory as the
 * Phase 11B.1 marketplace_search.php. Production overrides go through
 * .env vars and config caching.
 *
 * All values have safe defaults. Per dev §27 — "Provide safe defaults
 * and validation."
 */
return [

    /*
     |---------------------------------------------------------------------
     | Feature flags (per dev §28)
     |---------------------------------------------------------------------
     | Each flag turns off one module independently. Disabling any flag
     | must NOT break the product detail page — the section is simply
     | omitted from Inertia props.
     */
    'features' => [
        'enabled'                => env('RECOMMENDATIONS_ENABLED', true),
        'similar_products'       => env('RECOMMENDATIONS_SIMILAR_ENABLED', true),
        'frequently_bought'      => env('RECOMMENDATIONS_FBT_ENABLED', true),
        'customers_also_bought'  => env('RECOMMENDATIONS_ALSO_BOUGHT_ENABLED', true),
        'similar_services'       => env('RECOMMENDATIONS_SIMILAR_SERVICES_ENABLED', true),
        'analytics'              => env('RECOMMENDATIONS_ANALYTICS_ENABLED', true),
        'admin_curated'          => env('RECOMMENDATIONS_ADMIN_CURATED_ENABLED', true),
        'cart_recommendations'   => env('RECOMMENDATIONS_CART_ENABLED', false),  // §30 optional
    ],

    /*
     |---------------------------------------------------------------------
     | Result limits
     |---------------------------------------------------------------------
     */
    'limits' => [
        'similar_products'      => env('RECOMMENDATIONS_SIMILAR_LIMIT', 8),
        'frequently_bought'     => env('RECOMMENDATIONS_FBT_LIMIT', 4),
        'customers_also_bought' => env('RECOMMENDATIONS_ALSO_BOUGHT_LIMIT', 8),
        'similar_services'      => env('RECOMMENDATIONS_SIMILAR_SERVICES_LIMIT', 6),
    ],

    /*
     |---------------------------------------------------------------------
     | Similar Products scoring weights (per dev §5)
     |---------------------------------------------------------------------
     | All values are absolute scores added together. Per dev:
     | "Text/category similarity must be more important than popularity."
     | The category/tag weights deliberately outweigh the popularity floor.
     */
    'weights' => [
        'same_subcategory'        => env('REC_W_SAME_SUBCATEGORY', 50),
        'same_parent_category'    => env('REC_W_SAME_PARENT_CATEGORY', 25),
        'price_within_10_percent' => env('REC_W_PRICE_10', 30),
        'price_within_25_percent' => env('REC_W_PRICE_25', 15),
        'price_within_50_percent' => env('REC_W_PRICE_50', 5),
        'same_vendor'             => env('REC_W_SAME_VENDOR', 5),
        // Quality / popularity / freshness — small additive boosters; do not
        // override the category/tag dominance per dev §5
        'rating_per_point'        => env('REC_W_RATING', 2),    // up to 10 (5★ × 2)
        'popularity_log'          => env('REC_W_POPULARITY', 5),  // log10(orders+1) × 5
        'promotion_active'        => env('REC_W_PROMOTION', 3),
        'in_stock'                => env('REC_W_IN_STOCK', 2),
    ],

    /*
     |---------------------------------------------------------------------
     | Frequently Bought Together thresholds (per dev §9)
     |---------------------------------------------------------------------
     */
    'frequently_bought' => [
        'min_pair_orders'   => env('REC_FBT_MIN_PAIR_ORDERS', 2),
        'min_confidence'    => env('REC_FBT_MIN_CONFIDENCE', 0.1),    // P(B|A) ≥ 10%
        'min_support'       => env('REC_FBT_MIN_SUPPORT', 0.001),     // ≥ 0.1% of all orders
        'lookback_days'     => env('REC_FBT_LOOKBACK_DAYS', 180),
        'recency_half_life' => env('REC_FBT_HALF_LIFE_DAYS', 90),
    ],

    /*
     |---------------------------------------------------------------------
     | Customers Also Bought (per dev §11+§12)
     |---------------------------------------------------------------------
     | Privacy threshold — never publish recommendations derived from
     | fewer than N distinct customers.
     */
    'customers_also_bought' => [
        'min_distinct_customers' => env('REC_ALSO_BOUGHT_MIN_CUSTOMERS', 3),
        'lookback_days'          => env('REC_ALSO_BOUGHT_LOOKBACK_DAYS', 365),
    ],

    /*
     |---------------------------------------------------------------------
     | Qualifying order statuses (per dev §8)
     |---------------------------------------------------------------------
     | Same as the marketplace's real Order::STATUS_* constants.
     | Defined here for completeness, but the services read directly from
     | the Order model.
     */
    'qualifying_order_statuses' => [
        'paid', 'confirmed', 'shipped', 'delivered', 'completed',
    ],

    /*
     |---------------------------------------------------------------------
     | Cache & refresh
     |---------------------------------------------------------------------
     */
    'cache' => [
        'ttl_seconds' => env('REC_CACHE_TTL', 86400),  // 24h
    ],

    /*
     |---------------------------------------------------------------------
     | Eligibility (per dev §18) — applied uniformly across all rec types
     |---------------------------------------------------------------------
     */
    'eligibility' => [
        'exclude_out_of_stock' => env('REC_EXCLUDE_OOS', true),
    ],

    /*
     |---------------------------------------------------------------------
     | Analytics attribution window (per dev §21)
     |---------------------------------------------------------------------
     */
    'analytics' => [
        'attribution_window_days' => env('REC_ATTRIBUTION_DAYS', 7),
    ],
];
