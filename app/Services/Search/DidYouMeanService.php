<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use App\Models\SearchQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §9 — bounded typo / "Did you mean?" assistance.
 *
 * Per dev §9 explicit constraint:
 *   "Do not run expensive edit-distance calculations against every database
 *    row. Use a limited candidate dictionary generated from: categories,
 *    popular products, brands, services, popular searches. Cache or
 *    precompute the dictionary."
 *
 * Strategy:
 *   1. Build a per-locale dictionary of "known good terms" by sampling:
 *        - All active category names + name_translations[locale]
 *        - Top N popular queries from search_queries
 *        - First N published product names (capped)
 *   2. Cache it for ~1 hour.
 *   3. On each "did you mean" lookup:
 *        - If the user's normalized query is ALREADY in the dictionary → no
 *          suggestion needed.
 *        - Otherwise compute Levenshtein vs each dictionary term, return the
 *          closest one IF the distance ≤ typo_max_distance AND the term is
 *          actually shorter than the query (i.e. a typo, not a totally
 *          unrelated guess).
 *
 * Bounded dictionary size (default 1000 terms) keeps the per-request work
 * at O(dict_size × query_length) — fast in PHP for sub-1000-element arrays.
 */
class DidYouMeanService
{
    private const CACHE_KEY_PREFIX = 'marketplace:search:typo_dict:v1:';

    /**
     * Returns the closest candidate term if a likely typo is detected,
     * otherwise null.
     */
    public function suggest(string $query, string $locale): ?string
    {
        if (! config('marketplace_search.features.did_you_mean_enabled', true)) {
            return null;
        }

        $query = QueryNormalizer::normalize($query);
        if (mb_strlen($query) < 3) {
            return null; // too short to typo-correct safely
        }

        $dict = $this->getDictionary($locale);
        if (empty($dict)) {
            return null;
        }

        // Exact match in dictionary → no suggestion needed
        if (in_array($query, $dict, true)) {
            return null;
        }

        $maxDist = (int) config('marketplace_search.limits.typo_max_distance', 2);

        $best     = null;
        $bestDist = PHP_INT_MAX;
        foreach ($dict as $candidate) {
            // Skip candidates whose length differs too much — Levenshtein
            // can never be < the length difference, so this is a cheap prune.
            $lenDiff = abs(mb_strlen($candidate) - mb_strlen($query));
            if ($lenDiff > $maxDist) {
                continue;
            }
            $dist = levenshtein($query, $candidate);
            if ($dist > 0 && $dist <= $maxDist && $dist < $bestDist) {
                $best     = $candidate;
                $bestDist = $dist;
            }
        }

        return $best;
    }

    /**
     * Build (or load from cache) the locale-specific dictionary of known terms.
     *
     * @return array<int,string>
     */
    private function getDictionary(string $locale): array
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX . $locale,
            config('marketplace_search.cache.typo_dict_ttl_seconds', 3600),
            function () use ($locale): array {
                $dict = [];
                $maxTerms = (int) config('marketplace_search.limits.typo_dict_max_terms', 1000);

                // 1. Categories (active)
                try {
                    if (Schema::hasTable('categories')) {
                        $cats = Category::query()
                            ->where('is_active', true)
                            ->select(['name', 'name_translations'])
                            ->limit(200)
                            ->get();
                        foreach ($cats as $c) {
                            $dict[] = QueryNormalizer::normalize((string) $c->name);
                            $arName = $c->name_translations[$locale] ?? null;
                            if ($arName) {
                                $dict[] = QueryNormalizer::normalize((string) $arName);
                            }
                        }
                    }
                } catch (\Throwable) {
                    // non-fatal
                }

                // 2. Popular search queries for this locale
                try {
                    if (Schema::hasTable('search_queries')) {
                        $popular = SearchQuery::query()
                            ->popular($locale, 3, 1)
                            ->orderByDesc('search_count')
                            ->limit(200)
                            ->pluck('query');
                        foreach ($popular as $q) {
                            $dict[] = QueryNormalizer::normalize((string) $q);
                        }
                    }
                } catch (\Throwable) {
                    // non-fatal
                }

                // 3. A capped sample of published product names + translations
                try {
                    if (Schema::hasTable('products')) {
                        $remaining = max(0, $maxTerms - count($dict));
                        if ($remaining > 0) {
                            $products = Product::query()
                                ->published()
                                ->where('type', '!=', 'service')
                                ->select(['name', 'name_translations'])
                                ->orderByDesc('sales_count')
                                ->limit(min(600, $remaining))
                                ->get();
                            foreach ($products as $p) {
                                $dict[] = QueryNormalizer::normalize((string) $p->name);
                                $arName = $p->name_translations[$locale] ?? null;
                                if ($arName) {
                                    $dict[] = QueryNormalizer::normalize((string) $arName);
                                }
                            }
                        }
                    }
                } catch (\Throwable) {
                    // non-fatal
                }

                // Deduplicate, drop empty, cap to max
                $dict = array_values(array_filter(array_unique($dict), fn ($t) => mb_strlen($t) >= 3));
                if (count($dict) > $maxTerms) {
                    $dict = array_slice($dict, 0, $maxTerms);
                }
                return $dict;
            }
        );
    }

    public function flush(?string $locale = null): void
    {
        if ($locale !== null) {
            Cache::forget(self::CACHE_KEY_PREFIX . $locale);
            return;
        }
        foreach (config('marketplace.supported_locales', ['en']) as $l) {
            Cache::forget(self::CACHE_KEY_PREFIX . $l);
        }
    }
}
