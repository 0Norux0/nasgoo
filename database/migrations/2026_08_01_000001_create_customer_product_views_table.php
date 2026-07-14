<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.3 §8 §24 — customer_product_views.
 *
 * WHY A DEDICATED TABLE (not reuse recommendation_events):
 *   - recommendation_events is analytics-scoped (bulk aggregation queries,
 *     de-normalized recommendation_type, no unique constraint per user+product).
 *   - RecentlyViewedService needs a "most recent N distinct products per
 *     customer/session" query — this MUST hit a compound index on
 *     (user_id, viewed_at DESC) or (session_key, viewed_at DESC) with
 *     de-duplication semantics.
 *   - Mixing personal-history and analytics into one table creates two bad
 *     outcomes: (a) analytics queries scan personal rows, (b) personal
 *     queries scan analytics rows.
 *
 * Retention: pruned by `personalization:prune` command per dev §36.
 *   - authenticated user: 90 days (config-controlled)
 *   - guest session:      30 days (config-controlled)
 *
 * Deduplication: `session_key` OR `user_id` + `product_id` uniqueness would
 * lose the recency dimension. Instead we keep multiple rows and
 * deduplicate at read time in RecentlyViewedService (SELECT DISTINCT
 * product_id ORDER BY viewed_at DESC LIMIT N). This lets us count views
 * per product for the affinity signal.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('customer_product_views')) {
            return;
        }
        Schema::create('customer_product_views', function (Blueprint $table) {
            $table->id();
            // Nullable so guests can be tracked by session_key alone
            $table->unsignedBigInteger('user_id')->nullable();
            // Anonymous session key for guests (from cookie/session; NEVER an
            // IP or fingerprint). Rotated by session_regenerate_id per Laravel.
            $table->string('session_key', 64)->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('locale', 8);
            $table->string('device_category', 16)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            // Recent-views-per-user + recent-views-per-guest-session queries.
            // (user_id, viewed_at DESC) and (session_key, viewed_at DESC).
            $table->index(['user_id', 'viewed_at'], 'cpv_user_recent_idx');
            $table->index(['session_key', 'viewed_at'], 'cpv_session_recent_idx');
            // For affinity aggregation: count views per product per user.
            $table->index(['user_id', 'product_id'], 'cpv_user_product_idx');
            // For pruning: WHERE viewed_at < NOW - retention_days
            $table->index('viewed_at', 'cpv_viewed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_views');
    }
};
