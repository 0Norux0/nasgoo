<?php

declare(strict_types=1);

/**
 * Phase 11B.3 — personalization configuration.
 *
 * All weights, decay windows, retention periods, and cache TTLs live here.
 * Per dev §15 "Do not use unexplained hardcoded numbers throughout the code" —
 * the services read from this file, never inline literals.
 */
return [

    // ─── §33 feature flags ──────────────────────────────────────────────
    'features' => [
        'enabled'                  => env('PERSONALIZATION_ENABLED', true),
        'recently_viewed'          => env('PERSONALIZATION_RECENTLY_VIEWED_ENABLED', true),
        'continue_shopping'        => env('PERSONALIZATION_CONTINUE_SHOPPING_ENABLED', true),
        'recommended_for_you'      => env('PERSONALIZATION_RECOMMENDED_ENABLED', true),
        'category_affinity'        => env('PERSONALIZATION_CATEGORY_AFFINITY_ENABLED', true),
        'buy_again'                => env('PERSONALIZATION_BUY_AGAIN_ENABLED', true),
        'recommended_services'     => env('PERSONALIZATION_RECOMMENDED_SERVICES_ENABLED', true),
        'guest_personalization'    => env('PERSONALIZATION_GUEST_ENABLED', true),
        'behavior_tracking'        => env('PERSONALIZATION_BEHAVIOR_TRACKING_ENABLED', true),
        'analytics'                => env('PERSONALIZATION_ANALYTICS_ENABLED', true),
        'feedback_controls'        => env('PERSONALIZATION_FEEDBACK_CONTROLS_ENABLED', true),
        'guest_to_customer_merge'  => env('PERSONALIZATION_GUEST_MERGE_ENABLED', true),
    ],

    // ─── §21 per-user defaults (used when preferences row absent) ───────
    'defaults' => [
        'behavioral_personalization_enabled' => true,
        'guest_merge_enabled'                => true,
        'behavior_tracking_enabled'          => true,
    ],

    // ─── §11 §12 section priority + limits ──────────────────────────────
    'sections' => [
        // Authenticated priority order per dev §12
        'authenticated_priority' => [
            'continue_shopping',
            'recently_viewed',
            'recommended_for_you',
            'category_affinity',
            'buy_again',
        ],
        // Guest priority order per dev §12
        'guest_priority' => [
            'recently_viewed',
            'category_affinity',
            'recommended_for_you',   // session-scoped
        ],
        'limits' => [
            'recently_viewed'       => 12,
            'continue_shopping'     => 8,
            'recommended_for_you'   => 12,
            'category_affinity'     => 8,
            'buy_again'             => 8,
            'recommended_services'  => 6,
        ],
        // §11: cap total sections shown even if more have data
        'max_sections_shown' => 5,
        // §13: minimum candidates before a section renders (avoid one-item sections)
        'min_section_evidence' => 2,
    ],

    // ─── §14 affinity signal weights ────────────────────────────────────
    'affinity_weights' => [
        'completed_purchase' => 50,
        'repeat_purchase'    => 60,
        'add_to_cart'        => 15,
        'wishlist_add'       => 12,
        'recommendation_click' => 8,
        'product_view'       => 3,
        'category_view'      => 2,
        'search_term'        => 5,
        'impression'         => 0,   // no positive signal per dev §14
    ],

    // Cap per-event contribution (prevents refresh-spam dominance, dev §14)
    'affinity_caps' => [
        // Same (user, product) view: only first 5 views count for affinity
        'views_per_product'    => 5,
        // Same (user, category) search: cap
        'searches_per_category' => 10,
    ],

    // ─── §15 recency decay multipliers ──────────────────────────────────
    // Applied as: signal_weight × decay[bucket]. Newer buckets first.
    'recency_decay' => [
        'last_7_days'    => 1.0,
        'days_8_to_30'   => 0.6,
        'days_31_to_90'  => 0.3,
        'older_than_90'  => 0.0,   // ignored
    ],

    // ─── §36 retention (in days) ────────────────────────────────────────
    'retention' => [
        'customer_views_days'  => 90,
        'guest_views_days'     => 30,
        'feedback_expiry_days' => 90,   // "Not Interested" persists 90 days
        'affinity_stale_days'  => 90,   // rebuild prunes affinities older than this
    ],

    // ─── §27 cache ──────────────────────────────────────────────────────
    'cache' => [
        'homepage_ttl_seconds' => 300,   // 5 minutes; runtime eligibility recheck backs this up
        'affinity_ttl_seconds' => 900,   // 15 minutes
    ],

    // ─── §13 deduplication ─────────────────────────────────────────────
    'deduplication' => [
        // Higher-priority section claims a product first; lower-priority
        // sections must exclude it.
        'cross_section' => true,
        // Optional vendor diversity: at most N cards per vendor per section
        'max_per_vendor_per_section' => 4,
    ],

    // ─── §14 price band boundaries (minor units, KWD) ──────────────────
    'price_bands' => [
        'band_under_10_kwd'   => ['min' => 0,      'max' => 999],
        'band_10_25_kwd'      => ['min' => 1000,   'max' => 2499],
        'band_25_50_kwd'      => ['min' => 2500,   'max' => 4999],
        'band_50_100_kwd'     => ['min' => 5000,   'max' => 9999],
        'band_100_plus_kwd'   => ['min' => 10000,  'max' => PHP_INT_MAX],
    ],

    // ─── §17 Buy Again eligibility ────────────────────────────────────
    'buy_again' => [
        'min_days_since_purchase' => 7,   // avoid suggesting product they just bought yesterday
        'max_days_since_purchase' => 180,
    ],

    // ─── §31 UI layout ─────────────────────────────────────────────────
    'ui' => [
        'card_grid_columns' => [
            'mobile'  => 2,
            'tablet'  => 3,
            'desktop' => 4,
        ],
    ],
];
