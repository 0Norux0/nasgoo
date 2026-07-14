<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 §26 — Vendor Intelligence tables (additive).
 *
 * ─── vendor_intelligence_summaries ──────────────────────────────────
 *   Precomputed per-vendor rollup consumed by the vendor dashboard.
 *   One row per vendor, refreshed by the generation command. This is
 *   the "cache-that-outlives-Redis" — surviving cache flushes is
 *   important so the dashboard stays fast even on cold caches.
 *
 * ─── vendor_intelligence_alerts ─────────────────────────────────────
 *   Individual actionable alerts (out_of_stock, low_stock, missing_arabic,
 *   high_view_low_conversion, etc.). Lifecycle: active → dismissed / snoozed
 *   / resolved / expired (§32). Unique constraint prevents duplicates
 *   per (vendor, alert_type, entity_type, entity_id, status=active).
 *
 * ─── vendor_intelligence_feedback ───────────────────────────────────
 *   Vendor's dismiss/snooze decisions per suggestion_type + entity.
 *   Critical alerts may be un-dismissable; enforced in the service layer.
 *
 * ─── vendor_product_quality_scores ──────────────────────────────────
 *   Per-product quality score (0-100) with the missing_fields JSON,
 *   refreshed by the generation command.
 */
return new class extends Migration {

    public function up(): void
    {
        // ─── vendor_intelligence_summaries ───────────────────────────
        if (! Schema::hasTable('vendor_intelligence_summaries')) {
            Schema::create('vendor_intelligence_summaries', function (Blueprint $t) {
                $t->id();
                $t->foreignId('vendor_id')->constrained()->cascadeOnDelete();

                // Rollup counts consumed by the dashboard header cards
                $t->unsignedInteger('total_products')->default(0);
                $t->unsignedInteger('total_active_products')->default(0);
                $t->unsignedInteger('out_of_stock_count')->default(0);
                $t->unsignedInteger('low_stock_count')->default(0);
                $t->unsignedInteger('slow_moving_count')->default(0);
                $t->unsignedInteger('missing_arabic_count')->default(0);
                $t->unsignedInteger('missing_images_count')->default(0);
                $t->unsignedInteger('active_alerts_count')->default(0);

                // Store completion 0-100 + missing fields as CSV
                $t->unsignedTinyInteger('store_completion_score')->default(0);
                $t->text('store_missing_fields')->nullable();

                // Aggregate product quality (average across active products) 0-100
                $t->unsignedTinyInteger('avg_product_quality')->default(0);

                $t->timestamp('computed_at')->nullable();
                $t->timestamps();

                $t->unique('vendor_id', 'vis_vendor_uniq');
                $t->index('computed_at', 'vis_computed_at_idx');
            });
        }

        // ─── vendor_intelligence_alerts ──────────────────────────────
        if (! Schema::hasTable('vendor_intelligence_alerts')) {
            Schema::create('vendor_intelligence_alerts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $t->string('alert_type', 64);           // out_of_stock | low_stock | fast_moving_low_stock | slow_moving | stagnant | no_stock_tracking | missing_arabic | missing_images | high_view_low_conversion | wishlist_interest | cart_abandonment | promotion_opportunity
                $t->string('entity_type', 32)->nullable(); // product | variant | store
                $t->unsignedBigInteger('entity_id')->nullable();
                $t->string('priority', 16)->default('medium'); // critical | high | medium | low | info
                $t->string('status', 16)->default('active');   // active | dismissed | snoozed | resolved | expired
                $t->json('evidence')->nullable();        // {views: 340, purchases: 2, threshold: 10, ...}
                $t->timestamp('resolved_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();

                // Vendor-isolated queries + prevent duplicate active alerts
                $t->index(['vendor_id', 'status', 'priority'], 'via_vsp_idx');
                $t->index(['vendor_id', 'alert_type', 'entity_type', 'entity_id', 'status'], 'via_uniqness_idx');
                $t->index(['status', 'expires_at'], 'via_status_expiry_idx');
            });
        }

        // ─── vendor_intelligence_feedback ────────────────────────────
        if (! Schema::hasTable('vendor_intelligence_feedback')) {
            Schema::create('vendor_intelligence_feedback', function (Blueprint $t) {
                $t->id();
                $t->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $t->string('suggestion_type', 64);       // matches alert_type
                $t->string('entity_type', 32)->nullable();
                $t->unsignedBigInteger('entity_id')->nullable();
                $t->string('action', 16);                // dismissed | snoozed
                $t->timestamp('snoozed_until')->nullable();
                $t->timestamp('dismissed_at')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();

                $t->index(['vendor_id', 'suggestion_type', 'entity_type', 'entity_id'], 'vif_lookup_idx');
                $t->index('snoozed_until', 'vif_snooze_idx');
            });
        }

        // ─── vendor_product_quality_scores ───────────────────────────
        if (! Schema::hasTable('vendor_product_quality_scores')) {
            Schema::create('vendor_product_quality_scores', function (Blueprint $t) {
                $t->id();
                $t->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_id')->constrained()->cascadeOnDelete();
                $t->unsignedTinyInteger('score')->default(0); // 0-100
                $t->json('missing_fields')->nullable();       // ["arabic_title", "images", "specs"]
                $t->json('score_breakdown')->nullable();      // {core: 30, media: 15, i18n: 12, ...}
                $t->timestamp('computed_at')->nullable();
                $t->timestamps();

                $t->unique('product_id', 'vpqs_product_uniq');
                $t->index(['vendor_id', 'score'], 'vpqs_vendor_score_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_product_quality_scores');
        Schema::dropIfExists('vendor_intelligence_feedback');
        Schema::dropIfExists('vendor_intelligence_alerts');
        Schema::dropIfExists('vendor_intelligence_summaries');
    }
};
