<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — add customization snapshot columns to cart_items + order_items.
 *
 * `customization_fee_minor` snapshots the per-line extra fees from the
 * customer's customization selections (font choice +X, image upload +Y,
 * etc.). For cart_items it's recalculated whenever the customization
 * changes; for order_items it's frozen at checkout.
 *
 * `customization_status` on order_items is ORTHOGONAL to fulfillment_status.
 * A line item can be customization=customer_approved while fulfillment is
 * still unfulfilled (vendor hasn't shipped yet). Values:
 *   pending           — default; customer placed order, vendor hasn't started
 *   in_review         — vendor is preparing the design
 *   proof_uploaded    — vendor uploaded a proof, awaiting customer response
 *   customer_approved — customer signed off; production can start
 *   customer_rejected — customer rejected; needs revision
 *   in_production     — production is in progress
 *   completed         — customization work done (independent of shipping)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedInteger('customization_fee_minor')->default(0)->after('unit_price_minor');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('customization_fee_minor')->default(0)->after('supplier_cost_minor');
            $table->string('customization_status', 32)->default('pending')->after('fulfillment_status');
            $table->index('customization_status');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('customization_fee_minor');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['customization_status']);
            $table->dropColumn(['customization_fee_minor', 'customization_status']);
        });
    }
};
