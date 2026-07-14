<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Search\DidYouMeanService;
use App\Services\Search\MarketplaceSearchService;
use App\Services\Search\QueryNormalizer;
use App\Services\Search\SearchAnalyticsService;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11B.1 §10 — live search suggestions (enhanced from v11A.4).
 *
 * v11A.4 returned a flat search across products/categories/services using
 * a simple prefix LIKE. v11B.1 enhances this to:
 *   - Use MarketplaceSearchService for product ranking
 *   - Add Popular Searches group (anonymous aggregated)
 *   - Add Recent Searches group (per-user, authenticated only)
 *   - Add "Did you mean?" suggestion when no good match
 *   - Each group has a strict cap from config
 *   - All groups localized via translatedName()
 *   - Privacy: per-user recent never exposed to others
 *   - Excludes draft/unpublished/inactive items
 *
 * Per dev §28 security:
 *   - Min 2-char query, max 100-char input
 *   - Throttled at the route level (throttle:120,1)
 *   - Returns ONLY required columns
 *   - No raw HTML in any label
 *   - No private vendor/user data
 */
class SearchSuggestionController extends Controller
{
    public function __construct(
        private readonly MarketplaceSearchService $search,
        private readonly DidYouMeanService $dym,
        private readonly SearchAnalyticsService $analytics,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rawQ   = (string) $request->query('q', '');
        $locale = app()->getLocale();

        $minLen   = (int) config('marketplace_search.limits.min_query_length', 2);
        $maxLen   = (int) config('marketplace_search.limits.max_query_length', 100);
        $rawQ     = mb_substr($rawQ, 0, $maxLen);
        $normalized = QueryNormalizer::normalize($rawQ);

        // Below minimum length — return popular + recent groups only (lets the
        // UI show standing suggestions before the user types).
        if (mb_strlen($normalized) < $minLen) {
            return response()->json([
                'query'      => $normalized,
                'products'   => [],
                'categories' => [],
                'services'   => [],
                'popular'    => $this->analytics->getPopularForLocale($locale),
                'recent'     => $request->user()
                    ? $this->analytics->getRecentForUser($request->user(), $locale)
                    : [],
                'did_you_mean' => null,
                'total'      => 0,
            ]);
        }

        if (! config('marketplace_search.features.suggestions_enabled', true)) {
            return response()->json([
                'query'      => $normalized,
                'products'   => [], 'categories' => [], 'services' => [],
                'popular'    => [], 'recent' => [], 'did_you_mean' => null,
                'total'      => 0,
            ]);
        }

        $caps = [
            'products'   => (int) config('marketplace_search.limits.suggestion_products',   5),
            'categories' => (int) config('marketplace_search.limits.suggestion_categories', 3),
            'services'   => (int) config('marketplace_search.limits.suggestion_services',   3),
            'popular'    => (int) config('marketplace_search.limits.suggestion_popular',    3),
            'recent'     => (int) config('marketplace_search.limits.suggestion_recent',     3),
        ];

        // 1. Products — via MarketplaceSearchService (weighted relevance)
        $products = [];
        try {
            $productBuilder = $this->search->products($rawQ, $locale);
            if ($productBuilder !== null) {
                $rows = $productBuilder->limit($caps['products'])->get([
                    'id', 'slug', 'name', 'name_translations', 'price_minor', 'currency',
                ]);
                $products = $rows->map(fn (Product $p) => [
                    'id'       => $p->id,
                    'slug'     => $p->slug,
                    'name'     => $p->translatedName($locale),
                    'price'    => number_format($p->price_minor / 100, 2),
                    'currency' => $p->currency,
                    'href'     => "/products/{$p->slug}",
                ])->all();
            }
        } catch (\Throwable $e) {
            \Log::warning('v11B.1 suggestion products failed', ['error' => $e->getMessage()]);
            $products = [];
        }

        // 2. Categories — via search service helper (uses synonyms)
        $categories = [];
        try {
            $categories = $this->search->categories($rawQ, $locale, $caps['categories'])
                ->map(fn ($c) => [
                    'id'   => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->translatedName($locale),
                    'href' => "/products?category={$c->slug}",
                ])->all();
        } catch (\Throwable $e) {
            $categories = [];
        }

        // 3. Services — direct query (type=service + active service detail)
        $services = [];
        try {
            $like = QueryNormalizer::substringPattern($normalized);
            $services = Product::query()
                ->select(['id', 'slug', 'name', 'name_translations', 'price_minor', 'currency'])
                ->where('status', Product::STATUS_PUBLISHED)
                ->where('type', 'service')
                ->whereHas('serviceDetail', fn ($qb) => $qb->where('is_active', true))
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orderBy('name')
                ->limit($caps['services'])
                ->get()
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'slug'     => $s->slug,
                    'name'     => $s->translatedName($locale),
                    'price'    => number_format($s->price_minor / 100, 2),
                    'currency' => $s->currency,
                    'href'     => "/services/{$s->slug}",
                ])->all();
        } catch (\Throwable $e) {
            $services = [];
        }

        // 4. Popular searches (anonymous, blocklist-respecting)
        $popular = $this->analytics->getPopularForLocale($locale, $caps['popular']);

        // 5. Recent searches (user-scoped only)
        $recent = $request->user()
            ? $this->analytics->getRecentForUser($request->user(), $locale, $caps['recent'])
            : [];

        // 6. Did you mean — only when no main results
        $didYouMean = null;
        if (count($products) + count($categories) + count($services) === 0) {
            try {
                $didYouMean = $this->dym->suggest($rawQ, $locale);
            } catch (\Throwable) {
                $didYouMean = null;
            }
        }

        $total = count($products) + count($categories) + count($services);

        return response()->json([
            'query'        => $normalized,
            'products'     => $products,
            'categories'   => $categories,
            'services'     => $services,
            'popular'      => $popular,
            'recent'       => $recent,
            'did_you_mean' => $didYouMean,
            'total'        => $total,
        ]);
    }
}
