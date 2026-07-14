<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.2 §21 — recommendation_events: privacy-safe aggregated analytics.
 *
 * Stores impression / click / add_to_cart / purchase events for
 * recommendation effectiveness reporting. NO customer PII beyond:
 *   - `session_token` (SHA-256-hashed, NOT the raw session ID)
 *   - `user_id` (nullable, ONLY for attributing conversions — never displayed
 *     in admin reports; reports are aggregated across all users)
 *
 * Per dev §21 — "Do not store unnecessary personal identity."
 * Per dev §22 — admin analytics view shows aggregates only.
 *
 * Attribution window:
 *   - default 7 days (configurable via marketplace_recommendations config)
 *   - join: events where event_type IN ('impression','click','add_to_cart')
 *     within 7d before a 'purchase' event with the same recommended_product_id
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('recommendation_events')) {
            return;
        }
        Schema::create('recommendation_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 24);  // impression | click | add_to_cart | purchase
            $table->unsignedBigInteger('product_id');               // source product whose page rendered the rec
            $table->unsignedBigInteger('recommended_product_id');   // product the user saw / clicked / bought
            $table->string('recommendation_type', 24);              // similar | fbt | also_bought | similar_service
            $table->string('locale', 8)->nullable();
            $table->string('device_category', 16)->nullable();      // mobile | tablet | desktop
            $table->char('session_token', 64)->nullable();          // SHA-256 of session id, NEVER raw
            $table->unsignedBigInteger('user_id')->nullable();      // attribution-only, never displayed
            $table->timestamps();

            $table->index(['recommendation_type', 'event_type', 'created_at'], 'rec_events_report_idx');
            $table->index(['product_id', 'recommendation_type'], 'rec_events_source_idx');
            $table->index('recommended_product_id', 'rec_events_target_idx');
            $table->index('created_at', 'rec_events_recency_idx');

            // No FK on session_token (it's a hash); FK on user_id with nullOnDelete to preserve aggregate
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('recommended_product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_events');
    }
};
