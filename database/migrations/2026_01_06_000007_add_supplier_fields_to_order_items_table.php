<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — link order_items to supplier_orders + snapshot supplier cost.
 *
 * When checkout creates an order line for a dropshipping product, we snapshot
 * the supplier_cost on the line (just like commission/vendor_earning are
 * snapshotted in Phase 4) and link to the supplier_order created by
 * DropshipOrderCreator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('supplier_order_id')
                ->nullable()
                ->after('variant_id')
                ->constrained('supplier_orders')
                ->nullOnDelete();
            $table->unsignedInteger('supplier_cost_minor')->nullable()->after('vendor_earning_minor');

            $table->index('supplier_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_order_id']);
            $table->dropIndex(['supplier_order_id']);
            $table->dropColumn(['supplier_order_id', 'supplier_cost_minor']);
        });
    }
};
