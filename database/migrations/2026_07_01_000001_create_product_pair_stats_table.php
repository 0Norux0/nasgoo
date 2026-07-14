<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.2 §26 — product_pair_stats: co-occurrence aggregation table.
 *
 * Records how often each unordered pair of products appears together in
 * qualifying completed orders. Canonical form: product_a_id < product_b_id
 * (lexical) — this enforces uniqueness without storing (A,B) and (B,A)
 * separately, halving table size.
 *
 *   pair_count               = number of distinct orders containing both
 *   distinct_customer_count  = number of distinct customers who bought both
 *                              (in the same OR different orders; FBT uses
 *                              the same-order constraint when reading)
 *   support_numerator        = pair_count (stored verbatim for explainability)
 *   confidence is computed on read: pair_count / orders_containing(A)
 *
 * Per dev §8 — qualifying order statuses: paid / confirmed / shipped /
 * delivered / completed. The aggregation job filters by these statuses.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('product_pair_stats')) {
            return;
        }
        Schema::create('product_pair_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_a_id');
            $table->unsignedBigInteger('product_b_id');
            $table->unsignedInteger('pair_count')->default(0);
            $table->unsignedInteger('distinct_customer_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // Uniqueness on the canonical (a,b) tuple (a < b enforced by inserter)
            $table->unique(['product_a_id', 'product_b_id'], 'pair_stats_unique_pair');

            // Read path: WHERE product_a_id=? OR product_b_id=? ORDER BY pair_count
            $table->index('product_a_id', 'pair_stats_a_idx');
            $table->index('product_b_id', 'pair_stats_b_idx');
            $table->index(['pair_count'], 'pair_stats_count_idx');
            $table->index(['last_seen_at'], 'pair_stats_recency_idx');

            // FK cascade: deleting a product removes its pair stats
            $table->foreign('product_a_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_b_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pair_stats');
    }
};
