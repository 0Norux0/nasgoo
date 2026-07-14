<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 v10.8 — promotion snapshot columns.
 *
 * Pre-v10.8 orders persisted only coupon snapshots, not promotion
 * snapshots. Customer/vendor/admin order views had no way to display
 * "20% Summer Flash Sale — −22.00 KWD" matching what the customer saw
 * in cart and at checkout. This migration adds:
 *
 *   orders.promotion_discount_minor   — sum of per-line promotion discounts
 *   order_items.promotion_id           — FK to promotion that applied
 *   order_items.promotion_name         — text snapshot (survives promotion delete)
 *   order_items.promotion_discount_minor — per-line discount applied
 *   order_items.original_unit_price_minor — pre-promotion unit price
 *
 * All additive + defaulted; safe to deploy without touching existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'promotion_discount_minor')) {
                $table->unsignedInteger('promotion_discount_minor')
                    ->default(0)
                    ->after('discount_minor')
                    ->comment('Phase 10 v10.8: sum of per-line promotion discounts (separate from coupon_discount_minor)');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'promotion_id')) {
                $table->unsignedBigInteger('promotion_id')
                    ->nullable()
                    ->after('vendor_id');
                $table->index('promotion_id');
            }
            if (! Schema::hasColumn('order_items', 'promotion_name')) {
                $table->string('promotion_name')
                    ->nullable()
                    ->after('promotion_id')
                    ->comment('Snapshot — survives promotion deletion');
            }
            if (! Schema::hasColumn('order_items', 'promotion_discount_minor')) {
                $table->unsignedInteger('promotion_discount_minor')
                    ->default(0)
                    ->after('promotion_name');
            }
            if (! Schema::hasColumn('order_items', 'original_unit_price_minor')) {
                $table->unsignedInteger('original_unit_price_minor')
                    ->nullable()
                    ->after('unit_price_minor')
                    ->comment('Phase 10 v10.8: pre-promotion unit price; null when no promotion applied');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'original_unit_price_minor')) {
                $table->dropColumn('original_unit_price_minor');
            }
            if (Schema::hasColumn('order_items', 'promotion_discount_minor')) {
                $table->dropColumn('promotion_discount_minor');
            }
            if (Schema::hasColumn('order_items', 'promotion_name')) {
                $table->dropColumn('promotion_name');
            }
            if (Schema::hasColumn('order_items', 'promotion_id')) {
                $table->dropIndex(['promotion_id']);
                $table->dropColumn('promotion_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'promotion_discount_minor')) {
                $table->dropColumn('promotion_discount_minor');
            }
        });
    }
};
