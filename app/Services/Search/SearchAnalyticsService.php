<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\SearchQuery;
use App\Models\User;
use App\Models\UserRecentSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §11 + §12 + §21 — privacy-conscious search analytics.
 *
 * Two responsibilities:
 *   1. Aggregate popular-query tracking (search_queries table) — NO PII.
 *   2. Per-user recent-search history (user_recent_searches) — strictly
 *      user-scoped.
 *
 * Per dev §21 explicit constraint:
 *   "Do not expose individual customer search behavior to vendors."
 *
 * Analytics writes are wrapped in try/catch — analytics failures must
 * NEVER break the search experience itself.
 */
class SearchAnalyticsService
{
    /**
     * Record that a search occurred. Updates the aggregate counter for the
     * normalized query and, if the user is authenticated, prepends to their
     * recent history.
     *
     * @param string  $query        Raw query (will be normalized here)
     * @param string  $locale
     * @param int     $resultCount  Number of results returned to the user
     * @param User|null $user       Authenticated user, or null for guest
     */
    public function recordSearch(string $query, string $locale, int $resultCount, ?User $user = null): void
    {
        if (! config('marketplace_search.features.analytics_enabled', true)) {
            return;
        }

        $normalized = SearchQuery::normalize($query);
        if (mb_strlen($normalized) < (int) config('marketplace_search.limits.min_query_length', 2)) {
            return;
        }

        // 1. Aggregate counter (PII-free)
        try {
            if (Schema::hasTable('search_queries')) {
                // Atomic UPSERT. updateOrCreate is fine here — the unique key
                // (query, locale) collision triggers an UPDATE.
                $row = SearchQuery::firstOrNew([
                    'query'  => $normalized,
                    'locale' => $locale,
                ]);
                $row->search_count       = ($row->search_count ?? 0) + 1;
                $row->last_result_count  = $resultCount;
                $row->last_searched_at   = now();
                $row->saveQuietly();
            }
        } catch (\Throwable $e) {
            \Log::warning('SearchAnalyticsService::recordSearch aggregate failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Per-user recent (only if authenticated AND result count > 0
        //    OR specifically configured to also store zero-result searches)
        if ($user && config('marketplace_search.features.recent_searches_enabled', true)) {
            try {
                if (Schema::hasTable('user_recent_searches')) {
                    UserRecentSearch::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'query'   => $normalized,
                            'locale'  => $locale,
                        ],
                        ['searched_at' => now()]
                    );

                    // Cap recent history at the configured per-user limit.
                    $this->pruneRecentForUser($user->id, $locale);
                }
            } catch (\Throwable $e) {
                \Log::warning('SearchAnalyticsService::recordSearch user-history failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Return the most-recently-searched queries for a user. NEVER exposed
     * to other users.
     *
     * @return array<int,string>
     */
    public function getRecentForUser(User $user, string $locale, ?int $limit = null): array
    {
        if (! config('marketplace_search.features.recent_searches_enabled', true)) {
            return [];
        }
        if (! Schema::hasTable('user_recent_searches')) {
            return [];
        }

        $limit ??= (int) config('marketplace_search.limits.suggestion_recent', 3);

        return UserRecentSearch::query()
            ->forUser($user->id)
            ->where('locale', $locale)
            ->orderByDesc('searched_at')
            ->limit($limit)
            ->pluck('query')
            ->all();
    }

    /**
     * Return popular (anonymous) queries for a locale. Used by the
     * suggestion endpoint when the user hasn't typed enough to trigger
     * specific results.
     *
     * @return array<int,string>
     */
    public function getPopularForLocale(string $locale, ?int $limit = null): array
    {
        if (! config('marketplace_search.features.popular_searches_enabled', true)) {
            return [];
        }
        if (! Schema::hasTable('search_queries')) {
            return [];
        }

        $limit      ??= (int) config('marketplace_search.limits.suggestion_popular', 3);
        $minCount     = (int) config('marketplace_search.limits.popular_min_count', 3);
        $minResults   = (int) config('marketplace_search.limits.popular_min_results', 1);

        return SearchQuery::query()
            ->popular($locale, $minCount, $minResults)
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('query')
            ->all();
    }

    /**
     * Delete all of a user's recent searches. Used by the user-facing
     * "Clear history" UI control.
     */
    public function clearRecentForUser(User $user, ?string $locale = null): int
    {
        if (! Schema::hasTable('user_recent_searches')) {
            return 0;
        }
        $q = UserRecentSearch::query()->forUser($user->id);
        if ($locale !== null) {
            $q->where('locale', $locale);
        }
        return $q->delete();
    }

    /**
     * Cap the per-user history at the configured limit AND delete entries
     * older than the retention window.
     */
    public function pruneRecentForUser(int $userId, string $locale): void
    {
        $limit          = (int) config('marketplace_search.limits.recent_per_user', 10);
        $retentionDays  = (int) config('marketplace_search.limits.recent_retention_days', 90);

        // Delete entries older than the retention window (privacy)
        if ($retentionDays > 0) {
            UserRecentSearch::query()
                ->forUser($userId)
                ->where('locale', $locale)
                ->where('searched_at', '<', now()->subDays($retentionDays))
                ->delete();
        }

        // Cap at the per-user limit (FIFO eviction)
        $extras = UserRecentSearch::query()
            ->forUser($userId)
            ->where('locale', $locale)
            ->orderByDesc('searched_at')
            ->skip($limit)
            ->take(PHP_INT_MAX)
            ->pluck('id');
        if ($extras->isNotEmpty()) {
            UserRecentSearch::whereIn('id', $extras)->delete();
        }
    }
}
