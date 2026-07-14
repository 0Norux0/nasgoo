<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.2 v11B.2.1 — extend recommendation_events for purchase attribution.
 *
 * The v11B.2 schema had no way to tie a 'purchase' event to a specific
 * order_item, which made idempotency impossible (re-running the listener
 * on multiple status transitions would double-count). It also lacked a
 * way to mark an event as reversed after a refund/cancellation per the
 * dev's "documented refund rule" requirement.
 *
 * This migration adds:
 *   - order_item_id  (nullable FK to order_items, with unique-per-source
 *                     constraint so the same order line cannot generate
 *                     duplicate purchase attributions)
 *   - reversed_at    (nullable timestamp; set when a refund or cancellation
 *                     reverses an existing purchase attribution — see the
 *                     RecordPurchaseAttributionJob refund branch)
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('recommendation_events')) {
            return;
        }
        Schema::table('recommendation_events', function (Blueprint $table) {
            if (! Schema::hasColumn('recommendation_events', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('user_id');
                $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
                // Idempotency: a given (order_item_id, recommendation_type, source product)
                // can produce only ONE purchase event. Multiple status transitions on the
                // same order item collapse to the same row (insertOrIgnore-friendly).
                $table->unique(
                    ['order_item_id', 'event_type', 'product_id', 'recommendation_type'],
                    'rec_events_purchase_unique'
                );
            }
            if (! Schema::hasColumn('recommendation_events', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('order_item_id');
                $table->index('reversed_at', 'rec_events_reversed_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recommendation_events')) {
            return;
        }
        Schema::table('recommendation_events', function (Blueprint $table) {
            if (Schema::hasColumn('recommendation_events', 'order_item_id')) {
                $table->dropUnique('rec_events_purchase_unique');
                $table->dropForeign(['order_item_id']);
                $table->dropColumn('order_item_id');
            }
            if (Schema::hasColumn('recommendation_events', 'reversed_at')) {
                $table->dropIndex('rec_events_reversed_idx');
                $table->dropColumn('reversed_at');
            }
        });
    }
};
