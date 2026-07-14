<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §12 + §21 — aggregated search analytics.
 *
 * PRIVACY-FIRST design:
 *   - Stores ONLY the normalized query string + locale + counts
 *   - Does NOT store user_id, ip address, session_id, or any identifier
 *   - "Who searched what" is unavailable by design
 *   - "What is popular" is the only signal exposed
 *
 * Updates use atomic UPSERT (updateOrInsert) so high-traffic terms aren't
 * a write-contention hotspot. The SearchAnalyticsService is the sole writer.
 *
 * Popular searches surface only:
 *   - active marketplace terms (filterable by min_count threshold)
 *   - terms that produced at least one result (zero-result filter)
 *   - locale-scoped
 *   - non-blocked terms (admin can blocklist offensive entries)
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();

            // Normalized search term (lowercased + trimmed + collapsed
            // whitespace). 100 chars cap matches the input length limit
            // enforced by the suggestion controller.
            $table->string('query', 100);

            // Locale this query was run in.
            $table->string('locale', 8);

            // Total times this query has been executed across all users.
            $table->unsignedBigInteger('search_count')->default(0);

            // Result count from the MOST RECENT execution. Used to detect
            // zero-result queries that may need a synonym or content gap fix.
            $table->unsignedInteger('last_result_count')->default(0);

            // When this query was most recently executed.
            $table->timestamp('last_searched_at')->nullable();

            // Admin-managed blocklist for offensive / spammy terms that
            // should never appear in "Popular Searches" suggestions.
            $table->boolean('is_blocked')->default(false);

            $table->timestamps();

            // No duplicates per locale — the writer uses updateOrInsert on
            // (query, locale) so each pair is a single aggregated row.
            $table->unique(['query', 'locale'], 'search_queries_query_locale_unique');

            // Index for "give me the top N popular queries in this locale":
            // WHERE locale=? AND is_blocked=0 AND search_count >= ?
            // ORDER BY search_count DESC
            $table->index(['locale', 'is_blocked', 'search_count'], 'search_queries_popular_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
