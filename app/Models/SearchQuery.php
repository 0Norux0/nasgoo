<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11B.1 §12 + §21 — aggregated search query analytics.
 *
 * Privacy: this table stores only the normalized query text + locale +
 * counts. There is NO user_id, ip, or session column by design.
 *
 * The SearchAnalyticsService writes via atomic UPSERT (updateOrInsert).
 */
class SearchQuery extends Model
{
    use HasFactory;

    protected $table = 'search_queries';

    protected $fillable = [
        'query', 'locale', 'search_count', 'last_result_count',
        'last_searched_at', 'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'search_count'      => 'integer',
            'last_result_count' => 'integer',
            'last_searched_at'  => 'datetime',
            'is_blocked'        => 'boolean',
        ];
    }

    /**
     * Normalize a query: lowercase + trim + collapse whitespace.
     * Mirror of SearchSynonym::normalize for cross-service consistency.
     */
    public static function normalize(string $q): string
    {
        $q = trim($q);
        $q = preg_replace('/\s+/u', ' ', $q);
        return mb_strtolower($q);
    }

    /**
     * Scope: queries eligible for the "Popular Searches" suggestion group.
     * Excludes admin-blocked terms and zero-result spam.
     */
    public function scopePopular(Builder $query, string $locale, int $minCount = 3, int $minResults = 1): Builder
    {
        return $query
            ->where('locale', $locale)
            ->where('is_blocked', false)
            ->where('search_count', '>=', $minCount)
            ->where('last_result_count', '>=', $minResults);
    }
}
