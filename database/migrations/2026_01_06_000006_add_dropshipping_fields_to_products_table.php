<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — extend products table for dropshipping.
 *
 * Adds columns so a marketplace Product can represent a dropshipping listing
 * backed by a supplier_product. The Product `type` column already supports
 * 'simple'/'variable'/'digital' (Phase 3); we add 'dropship' as a new value
 * — handled at the application layer, no DB enum constraint to add.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_product_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('supplier_products')
                ->nullOnDelete();
            $table->foreignId('supplier_platform_id')
                ->nullable()
                ->after('supplier_product_id')
                ->constrained('supplier_platforms')
                ->nullOnDelete();

            $table->unsignedInteger('supplier_cost_minor')->nullable()->after('cost_price_minor');
            $table->string('fulfillment_mode')->default('vendor_self')->after('supplier_cost_minor');
            //   vendor_self   — physical/regular product, vendor fulfills
            //   dropship_manual — dropship; vendor places supplier order manually
            //   dropship_admin  — dropship; admin places supplier order
            //   dropship_api    — dropship; auto-placed via API (future)

            $table->unsignedSmallInteger('estimated_delivery_days')->nullable()->after('fulfillment_mode');

            $table->index('fulfillment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_product_id']);
            $table->dropForeign(['supplier_platform_id']);
            $table->dropIndex(['fulfillment_mode']);
            $table->dropColumn([
                'supplier_product_id',
                'supplier_platform_id',
                'supplier_cost_minor',
                'fulfillment_mode',
                'estimated_delivery_days',
            ]);
        });
    }
};
