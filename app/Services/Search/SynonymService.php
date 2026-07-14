<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\SearchSynonym;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §8 — synonym expansion.
 *
 * Reads the search_synonyms table (locale-scoped, active only) and returns
 * the candidate set for a given query term. Bidirectional — a stored row
 * (term="phone", synonym="mobile") covers both lookup directions.
 *
 * Caching: the full active synonym set per locale is cached for the duration
 * configured in config('marketplace_search.cache.synonyms_ttl_seconds').
 * Cache is invalidated by SearchSynonym observer on save/delete (admin
 * panel writes; v11B.1 may not yet have the observer wired — admin actions
 * should call SynonymService::flush() explicitly until v11B.2 adds it).
 */
class SynonymService
{
    private const CACHE_KEY_PREFIX = 'marketplace:search:synonyms:v1:';

    /**
     * Expand a query term into [original, ...synonyms].
     *
     * @return array<int,string> Original term first, then all distinct synonyms
     */
    public function expand(string $term, string $locale): array
    {
        $term = QueryNormalizer::normalize($term);
        if ($term === '') {
            return [];
        }

        if (! config('marketplace_search.features.synonyms_enabled', true)) {
            return [$term];
        }

        $map = $this->getSynonymMap($locale);
        $expanded = [$term];

        // Bidirectional lookup
        if (isset($map[$term])) {
            foreach ($map[$term] as $syn) {
                if (! in_array($syn, $expanded, true)) {
                    $expanded[] = $syn;
                }
            }
        }

        return $expanded;
    }

    /**
     * Get the term→[synonyms] map for a locale. Cached.
     */
    private function getSynonymMap(string $locale): array
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX . $locale,
            config('marketplace_search.cache.synonyms_ttl_seconds', 3600),
            function () use ($locale): array {
                if (! Schema::hasTable('search_synonyms')) {
                    return [];
                }
                $pairs = SearchSynonym::query()
                    ->where('locale', $locale)
                    ->where('is_active', true)
                    ->get(['term', 'synonym']);

                $map = [];
                foreach ($pairs as $p) {
                    // Bidirectional: term → [synonym], synonym → [term]
                    $map[$p->term][]    = $p->synonym;
                    $map[$p->synonym][] = $p->term;
                }
                return $map;
            }
        );
    }

    /**
     * Clear the synonym cache for a locale (or all locales).
     */
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
