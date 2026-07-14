<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.3 §14 §24 — customer_affinities.
 *
 * Stores precomputed affinity scores per (customer, dimension) — one row per
 * category/vendor/price-band pair. Read during homepage rendering; NEVER
 * computed synchronously per request (dev §25).
 *
 * Rebuild: `personalization:rebuild [--user=]` (dev §25).
 * Retention: rebuild replaces rows; older-than-N-days rows without recent
 * signal are pruned by `personalization:prune`.
 *
 * Score semantics (dev §14):
 *   score = Σ(signal_weight × recency_multiplier) over the retention window,
 *   capped per-event to prevent refresh-spam dominance.
 * Concrete formula documented in CustomerAffinityService.
 *
 * Guest affinities are NOT stored here — they live transiently in
 * GuestPersonalizationService (session-backed) per dev §3 "data minimization".
 * Only authenticated customers persist affinities.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('customer_affinities')) {
            return;
        }
        Schema::create('customer_affinities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('dimension', 24);   // 'category' | 'vendor' | 'price_band'
            $table->unsignedBigInteger('dimension_id')->nullable();  // FK to category/vendor; null for price_band
            $table->string('dimension_key', 64)->nullable();  // e.g. 'band_50_100_kwd'
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('signal_count')->default(0);
            $table->timestamp('last_signal_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // One score per (user, dimension, dimension_id|dimension_key).
            // dimension_id is nullable so we use a compound unique that
            // includes dimension_key.
            $table->unique(
                ['user_id', 'dimension', 'dimension_id', 'dimension_key'],
                'ca_user_dim_unique'
            );
            $table->index(['user_id', 'score'], 'ca_user_score_idx');
            $table->index('last_signal_at', 'ca_last_signal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_affinities');
    }
};
