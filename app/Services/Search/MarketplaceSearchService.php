<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 11B.1 §4 + §5 — weighted relevance ranking.
 *
 * Transparent, deterministic scoring. No ML, no opaque models. Every
 * score component comes from a documented weight in config/marketplace_search.php.
 *
 * Scoring formula (per dev §4-§5):
 *
 *   total_score =
 *       title_exact   × W_title_exact   (1 if name matches query exactly, else 0)
 *     + title_prefix  × W_title_prefix  (1 if name starts with query, else 0)
 *     + title_partial × W_title_partial (1 if name contains query, else 0)
 *     + ar_title      × W_ar_title      (1 if name_translations.ar contains query, else 0)
 *     + category      × W_category      (1 if category name matches, else 0)
 *     + description   × W_description   (1 if short_description contains query, else 0)
 *     + in_stock      × W_in_stock      (1 if stock>0 or !track_stock, else 0)
 *     + rating        × W_rating_max    (rating_avg / 5.0)
 *     + popularity    × W_popularity_max (log(1+sales_count) / log(1000), capped)
 *     + promotion     × W_promotion     (1 if active promo, else 0 — TODO promo column)
 *     + freshness     × W_freshness     (1 if published <30d, else 0)
 *
 * Per dev §5:
 *   - "Text relevance MUST remain more important than popularity"
 *     → defaults set title_exact=100 vs popularity_max=3
 *   - "Avoid allowing one signal to dominate unfairly"
 *     → numerical signals are capped/normalized
 *
 * Per dev §29 test contracts:
 *   - Exact title match ranks first.
 *   - Title prefix ranks above description-only match.
 *   - Category match works.
 *   - Arabic product-title search works.
 *   - Popular unrelated products do not outrank strongly relevant items.
 *   - Unpublished products are excluded.
 *   - Suspended-vendor products are excluded.
 *
 * The service returns an Eloquent Builder so the caller can apply
 * additional filters (price, vendor, rating-floor) and paginate.
 */
class MarketplaceSearchService
{
    public function __construct(
        private readonly SynonymService $synonyms,
    ) {
    }

    /**
     * Build a query for product search results, ordered by weighted relevance.
     *
     * @param string $query   Raw user query (will be normalized)
     * @param string $locale  Current locale (used for Arabic-title matching)
     * @return Builder|null   null if query too short — caller may use no-query default ordering
     */
    public function products(string $query, string $locale): ?Builder
    {
        $minLen     = (int) config('marketplace_search.limits.min_query_length', 2);
        $normalized = QueryNormalizer::normalize($query);
        if (mb_strlen($normalized) < $minLen) {
            return null;
        }

        $weights = config('marketplace_search.weights', []);
        $w       = fn (string $k): float => (float) ($weights[$k] ?? 0);

        // Get the synonym candidate set (original first, then synonyms)
        $candidates = $this->synonyms->expand($normalized, $locale);
        $primary    = $candidates[0];
        // Patterns
        $exactCandidates  = $candidates;                                                    // exact match against any
        $prefixCandidates = array_map(fn ($c) => QueryNormalizer::prefixPattern($c), $candidates);
        $substringCandidates = array_map(fn ($c) => QueryNormalizer::substringPattern($c), $candidates);

        // Build the scoring expression incrementally using whereRaw + select.
        // We use a single CASE expression for the score so MySQL can sort
        // by the computed column on the same row read — no per-row PHP work.
        //
        // Notes:
        //  - We avoid SQL string interpolation; all dynamic values are passed
        //    as bindings.
        //  - We use LOWER(...) wrapping because the existing convention from
        //    Phase 9 v9.4 is portable across MySQL collation defaults + SQLite/Postgres.
        //  - For Arabic-title match, we use JSON_EXTRACT on name_translations.

        $bindings = [];
        $scoreParts = [];

        // 1. Title exact (any synonym)
        $exactPlaceholders = implode(',', array_fill(0, count($exactCandidates), '?'));
        $scoreParts[] = "(CASE WHEN LOWER(products.name) IN ($exactPlaceholders) THEN ? ELSE 0 END)";
        foreach ($exactCandidates as $c) $bindings[] = $c;
        $bindings[] = $w('title_exact_match');

        // 2. Title prefix (any synonym)
        $prefixCase = collect($prefixCandidates)->map(fn () => 'LOWER(products.name) LIKE ?')->implode(' OR ');
        $scoreParts[] = "(CASE WHEN ($prefixCase) THEN ? ELSE 0 END)";
        foreach ($prefixCandidates as $p) $bindings[] = $p;
        $bindings[] = $w('title_prefix_match');

        // 3. Title partial (any synonym)
        $partialCase = collect($substringCandidates)->map(fn () => 'LOWER(products.name) LIKE ?')->implode(' OR ');
        $scoreParts[] = "(CASE WHEN ($partialCase) THEN ? ELSE 0 END)";
        foreach ($substringCandidates as $p) $bindings[] = $p;
        $bindings[] = $w('title_partial_match');

        // 4. Arabic title match — JSON_EXTRACT path. Skip if locale != ar
        //    (avoids unnecessary JSON parsing cost).
        if ($locale === 'ar') {
            $arPath = '$.ar';
            $arPartialCase = collect($substringCandidates)
                ->map(fn () => "LOWER(JSON_UNQUOTE(JSON_EXTRACT(products.name_translations, ?))) LIKE ?")
                ->implode(' OR ');
            $scoreParts[] = "(CASE WHEN ($arPartialCase) THEN ? ELSE 0 END)";
            foreach ($substringCandidates as $p) {
                $bindings[] = $arPath;
                $bindings[] = $p;
            }
            $bindings[] = $w('arabic_title_match');
        }

        // 5. Description partial (only primary candidate, not synonyms — keep it
        //    cheap, descriptions can be long). v11B.1.1: when locale=ar, also
        //    match Arabic short_description from the JSON column.
        $scoreParts[] = "(CASE WHEN LOWER(products.short_description) LIKE ? THEN ? ELSE 0 END)";
        $bindings[]   = QueryNormalizer::substringPattern($primary);
        $bindings[]   = $w('description_match');

        if ($locale === 'ar') {
            $scoreParts[] = "(CASE WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(products.short_description_translations, ?))) LIKE ? THEN ? ELSE 0 END)";
            $bindings[]   = '$.ar';
            $bindings[]   = QueryNormalizer::substringPattern($primary);
            $bindings[]   = $w('description_match');
        }

        // 6. In-stock boost: stock > 0 OR !track_stock
        $scoreParts[] = "(CASE WHEN (products.track_stock = 0 OR products.stock > 0) THEN ? ELSE 0 END)";
        $bindings[]   = $w('in_stock_boost');

        // 7. Rating boost (capped): rating_avg/5 × rating_max
        $scoreParts[] = "((COALESCE(products.rating_avg, 0) / 5.0) * ?)";
        $bindings[]   = $w('rating_boost_max');

        // 8. Popularity boost (capped via log scaling): log(1 + sales_count) / log(1000)
        //    LEAST() caps at 1.0 so a runaway sales_count can't dominate.
        $scoreParts[] = "(LEAST(LN(1 + COALESCE(products.sales_count, 0)) / LN(1000), 1.0) * ?)";
        $bindings[]   = $w('popularity_boost_max');

        // 9. Freshness boost: published in last 30 days
        $scoreParts[] = "(CASE WHEN products.published_at >= ? THEN ? ELSE 0 END)";
        $bindings[]   = now()->subDays(30)->toDateTimeString();
        $bindings[]   = $w('freshness_boost');

        // 10. Promotion boost — featured_until in future as proxy until promo
        //     column is added in v11B.x. Simple AND deterministic.
        $scoreParts[] = "(CASE WHEN products.featured = 1 AND (products.featured_until IS NULL OR products.featured_until >= ?) THEN ? ELSE 0 END)";
        $bindings[]   = now()->toDateTimeString();
        $bindings[]   = $w('promotion_boost');

        // 11. Category match — JOIN-less subquery: products.category_id IN (
        //       SELECT id FROM categories WHERE LOWER(name) LIKE ? OR JSON_EXTRACT(...) LIKE ?
        //     )
        //     Keep it bounded — categories table is small.
        $catPlaceholders = collect($substringCandidates)->map(fn () => 'LOWER(name) LIKE ?')->implode(' OR ');
        $scoreParts[] = "(CASE WHEN products.category_id IN (SELECT id FROM categories WHERE ($catPlaceholders)) THEN ? ELSE 0 END)";
        foreach ($substringCandidates as $p) $bindings[] = $p;
        $bindings[] = $w('category_match');

        $scoreExpr = '(' . implode(' + ', $scoreParts) . ')';

        // Build the eligibility filter: only return products with score > 0
        // (relevance match) OR the user is browsing-without-query (caller
        // passes empty $query which we've already short-circuited above).
        //
        // We also want to exclude items where there's no text-relevance signal
        // at all (otherwise popular unrelated items would still appear with
        // popularity boost only). Enforced via WHERE on the text-relevance
        // OR clauses.

        $textRelevanceOr = [];
        $textBindings    = [];

        // Reuse the exact/prefix/partial/category clauses as a WHERE filter
        $textRelevanceOr[] = "LOWER(products.name) IN ($exactPlaceholders)";
        foreach ($exactCandidates as $c) $textBindings[] = $c;

        $textRelevanceOr[] = $prefixCase;
        foreach ($prefixCandidates as $p) $textBindings[] = $p;

        $textRelevanceOr[] = $partialCase;
        foreach ($substringCandidates as $p) $textBindings[] = $p;

        if ($locale === 'ar') {
            $textRelevanceOr[] = $arPartialCase;
            foreach ($substringCandidates as $p) {
                $textBindings[] = $arPath;
                $textBindings[] = $p;
            }
        }

        $textRelevanceOr[] = "products.category_id IN (SELECT id FROM categories WHERE ($catPlaceholders))";
        foreach ($substringCandidates as $p) $textBindings[] = $p;

        $textRelevanceOr[] = "LOWER(products.short_description) LIKE ?";
        $textBindings[]    = QueryNormalizer::substringPattern($primary);

        // Phase 11B.1 v11B.1.1 §7 — Arabic short_description eligibility
        // (lets an Arabic-only product be found by Arabic description text).
        if ($locale === 'ar') {
            $textRelevanceOr[] = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(products.short_description_translations, ?))) LIKE ?";
            $textBindings[]    = '$.ar';
            $textBindings[]    = QueryNormalizer::substringPattern($primary);
        }

        $textRelevanceWhere = '(' . implode(' OR ', $textRelevanceOr) . ')';

        // Final query: start from Product::query() + published scope + service exclusion,
        // add the WHERE filter, add the computed score column, order by score DESC then
        // sales_count DESC as a tiebreaker.
        $builder = Product::query()
            ->published()
            ->where('type', '!=', Product::TYPE_SERVICE)
            ->whereRaw($textRelevanceWhere, $textBindings)
            ->addSelect('products.*')
            ->selectRaw("$scoreExpr AS _search_score", $bindings)
            ->orderByDesc('_search_score')
            ->orderByDesc('sales_count');

        return $builder;
    }

    /**
     * Lightweight category search — used by suggestion controller.
     * Returns matching categories ordered by exact > prefix > partial.
     *
     * @return \Illuminate\Support\Collection<int,Category>
     */
    public function categories(string $query, string $locale, int $limit = 5): \Illuminate\Support\Collection
    {
        $normalized = QueryNormalizer::normalize($query);
        if ($normalized === '') return collect();

        $candidates = $this->synonyms->expand($normalized, $locale);
        $substring  = array_map(fn ($c) => QueryNormalizer::substringPattern($c), $candidates);

        $sql      = collect($substring)->map(fn () => 'LOWER(name) LIKE ?')->implode(' OR ');
        $bindings = $substring;

        return Category::query()
            ->where('is_active', true)
            ->whereRaw("($sql)", $bindings)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'slug', 'name', 'name_translations']);
    }
}
