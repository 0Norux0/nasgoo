<?php

declare(strict_types=1);

/**
 * Phase 11B.1 §4 + §22 + §25 — search configuration.
 *
 * Centralized settings for the smart-search module. Per dev §4:
 *   "Do not hard-code unexplained magic numbers throughout controllers.
 *    Store weights in: configuration / database settings / dedicated admin-
 *    managed search configuration. Use safe defaults. Document every weight."
 *
 * Per dev §25 (feature flags): each major capability can be disabled at
 * the config level for controlled rollback without code removal.
 *
 * Per dev §22 (admin search configuration): an admin UI can override these
 * values via the settings table (out of scope for v11B.1 — values come from
 * env vars or this config file. v11B.2 may add the Filament admin panel.)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Relevance scoring weights (§4 §5)
    |--------------------------------------------------------------------------
    |
    | Each weight is a numeric multiplier applied to a deterministic signal.
    | Higher = stronger ranking influence. Text relevance MUST outweigh
    | popularity (per §5: "Text relevance must remain more important than
    | popularity. A popular unrelated item must not outrank a strongly
    | relevant item.").
    |
    | All weights are non-negative. Setting to 0 disables that signal.
    |
    | Score formula (transparent, no opaque ML):
    |   score = sum(weight × signal_value) where signal_value is normalized
    |   to [0, 1] except for text-match weights which are 0 or 1.
    |
    | DEFAULTS chosen so:
    |   - title.exact dominates
    |   - title.prefix > title.partial > category > tag > description
    |   - in-stock and freshness add small boosts
    |   - rating/sales boosts capped — cannot override relevance
    */
    'weights' => [
        // Text-match signals (mutually exclusive — only ONE applies per item)
        'title_exact_match'   => (float) env('SEARCH_W_TITLE_EXACT',   100.0),
        'title_prefix_match'  => (float) env('SEARCH_W_TITLE_PREFIX',   70.0),
        'title_partial_match' => (float) env('SEARCH_W_TITLE_PARTIAL',  40.0),
        'arabic_title_match'  => (float) env('SEARCH_W_AR_TITLE',       40.0),  // same as partial
        'category_match'      => (float) env('SEARCH_W_CATEGORY',       25.0),
        'tag_brand_match'     => (float) env('SEARCH_W_TAG',            15.0),
        'description_match'   => (float) env('SEARCH_W_DESCRIPTION',     8.0),

        // Quality / availability / freshness boosts (additive, all bounded)
        'in_stock_boost'      => (float) env('SEARCH_W_IN_STOCK',        5.0),
        'rating_boost_max'    => (float) env('SEARCH_W_RATING',          3.0),  // scaled by rating_avg/5
        'popularity_boost_max'=> (float) env('SEARCH_W_POPULARITY',      3.0),  // scaled by log(sales+1)
        'promotion_boost'     => (float) env('SEARCH_W_PROMO',           2.0),
        'freshness_boost'     => (float) env('SEARCH_W_FRESH',           1.0),  // recent <30d
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags (§25)
    |--------------------------------------------------------------------------
    |
    | All features default to ON. Disable any of these for controlled rollback
    | of the corresponding capability without code removal.
    */
    'features' => [
        'smart_search_enabled'    => (bool) env('SEARCH_FEATURE_SMART',           true),
        'suggestions_enabled'     => (bool) env('SEARCH_FEATURE_SUGGESTIONS',     true),
        'synonyms_enabled'        => (bool) env('SEARCH_FEATURE_SYNONYMS',        true),
        'did_you_mean_enabled'    => (bool) env('SEARCH_FEATURE_DID_YOU_MEAN',    true),
        'recent_searches_enabled' => (bool) env('SEARCH_FEATURE_RECENT',          true),
        'popular_searches_enabled'=> (bool) env('SEARCH_FEATURE_POPULAR',         true),
        'facets_enabled'          => (bool) env('SEARCH_FEATURE_FACETS',          true),
        'analytics_enabled'       => (bool) env('SEARCH_FEATURE_ANALYTICS',       true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits (§10 §11 §22 §28)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        // Query input
        'min_query_length'        => (int) env('SEARCH_MIN_QUERY_LEN',     2),
        'max_query_length'        => (int) env('SEARCH_MAX_QUERY_LEN',   100),

        // Suggestion group caps
        'suggestion_products'     => (int) env('SEARCH_SUG_PRODUCTS',      5),
        'suggestion_categories'   => (int) env('SEARCH_SUG_CATEGORIES',    3),
        'suggestion_services'     => (int) env('SEARCH_SUG_SERVICES',      3),
        'suggestion_vendors'      => (int) env('SEARCH_SUG_VENDORS',       0),  // off by default for MVP
        'suggestion_popular'      => (int) env('SEARCH_SUG_POPULAR',       3),
        'suggestion_recent'       => (int) env('SEARCH_SUG_RECENT',        3),

        // Catalog results
        'catalog_per_page'        => (int) env('SEARCH_CATALOG_PER_PAGE', 24),

        // Recent search history
        'recent_per_user'         => (int) env('SEARCH_RECENT_PER_USER',  10),
        'recent_retention_days'   => (int) env('SEARCH_RECENT_DAYS',      90),

        // Popular search thresholds
        'popular_min_count'       => (int) env('SEARCH_POPULAR_MIN',       3),
        'popular_min_results'     => (int) env('SEARCH_POPULAR_MIN_RES',   1),  // exclude zero-result spam

        // Did-you-mean
        'typo_max_distance'       => (int) env('SEARCH_TYPO_MAX_DIST',     2),
        'typo_dict_max_terms'     => (int) env('SEARCH_TYPO_DICT_MAX',  1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache durations (seconds) — §24
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'synonyms_ttl_seconds'      => (int) env('SEARCH_CACHE_SYNONYMS',  3600),  // 1 hour
        'typo_dict_ttl_seconds'     => (int) env('SEARCH_CACHE_TYPO',      3600),
        'popular_queries_ttl_seconds'=> (int) env('SEARCH_CACHE_POPULAR',  600),   // 10 min (lower because of frequent updates)
        'facets_ttl_seconds'        => (int) env('SEARCH_CACHE_FACETS',     60),   // very short — counts move with stock/promotions
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocklist (§22) — terms that never appear in popular suggestions
    |--------------------------------------------------------------------------
    | Admin can extend this via search_queries.is_blocked = 1
    */
    'blocked_terms_default' => [
        // Generic spammy / offensive defaults. Marketplace-specific terms
        // should be added by the admin via the Filament panel (v11B.2).
        // Leave empty in v11B.1 — admins control via the DB column.
    ],

];
