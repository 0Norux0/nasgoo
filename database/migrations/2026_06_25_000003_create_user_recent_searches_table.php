<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §11 — per-user recent searches.
 *
 * Strictly user-scoped — the (user_id, query, locale) composite unique
 * index ensures a single user can't be queried for "what does this OTHER
 * user search for". The frontend never receives another user's history.
 *
 * Retention: enforced by SearchAnalyticsService::pruneOldRecent() which
 * caps the count at the per-user limit AND deletes entries older than
 * the configured retention window. Admin can clear all entries for a
 * user via DELETE; users can clear their own via DELETE /search/recent.
 *
 * Guest history is NEVER stored server-side — that flow uses browser
 * localStorage only.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('user_recent_searches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // Normalized search query (same normalization as search_queries).
            $table->string('query', 100);

            $table->string('locale', 8);

            // When the user last performed this search. On duplicate (per
            // user_id+query+locale) we UPDATE this timestamp rather than
            // INSERTing a new row.
            $table->timestamp('searched_at')->useCurrent();

            $table->timestamps();

            // One row per (user, query, locale). Repeats update the
            // searched_at timestamp.
            $table->unique(['user_id', 'query', 'locale'], 'user_recent_searches_unique');

            // Foreign key — CASCADE so deleting a user purges their history
            // (privacy: no orphaned search records).
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Lookup pattern: WHERE user_id=? AND locale=? ORDER BY searched_at DESC LIMIT N
            $table->index(['user_id', 'locale', 'searched_at'], 'user_recent_searches_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recent_searches');
    }
};
