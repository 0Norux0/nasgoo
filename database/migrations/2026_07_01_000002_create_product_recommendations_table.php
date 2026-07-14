<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.2 §23+§26 — product_recommendations: precomputed cache.
 *
 * Recommendations are computed offline by the `recommendations:generate`
 * command (or after qualifying order events) and stored here. Customer
 * page loads do a single index-hit lookup instead of recomputing scores.
 *
 * `recommendation_type`:
 *   'similar'         — Similar Products (category/price/tag scoring)
 *   'fbt'             — Frequently Bought Together (same-order co-occurrence)
 *   'also_bought'     — Customers Also Bought (broader customer co-purchase)
 *   'similar_service' — Similar Services (services share Product table)
 *
 * `evidence_count`:  for FBT this is pair_count; for similar this is 0
 *                    (purely scored); for also_bought this is distinct_customers
 *
 * `confidence`: stored only for co-occurrence types (fbt / also_bought)
 *
 * `score`: final unified score used for ORDER BY within type.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('product_recommendations')) {
            return;
        }
        Schema::create('product_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('recommended_product_id');
            $table->string('recommendation_type', 24);
            $table->float('score')->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->float('confidence')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Uniqueness: one row per (source, recommended, type)
            $table->unique(
                ['product_id', 'recommended_product_id', 'recommendation_type'],
                'product_recommendations_unique'
            );

            // Read path: WHERE product_id=? AND type=? ORDER BY score DESC
            $table->index(['product_id', 'recommendation_type', 'score'], 'product_recommendations_read_idx');

            // Refresh path: WHERE expires_at < NOW() (refresh-stale command)
            $table->index('expires_at', 'product_recommendations_expiry_idx');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('recommended_product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommendations');
    }
};
