<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 v9.3 — per-line coupon allocation breakdown.
 *
 * The order-level coupon discount must be allocated across order_items
 * for financial reconciliation:
 *
 *   - vendor needs to see how much of the discount came off THEIR line
 *   - vendor earnings must be computed on the NET (post-allocation) line
 *     total, not the gross — otherwise vendor earns more than the
 *     customer paid
 *   - refund partial cancellation needs the per-line snapshot to
 *     compute refund amount per item
 *
 * The allocation rule (CheckoutService::placeOrder):
 *
 *   allocated[i] = floor(coupon_discount * line_total[i] / subtotal)
 *
 * for lines 0..n-2; the last line receives whatever remains so the sum
 * exactly equals the coupon discount (deterministic rounding).
 *
 * For lines without a coupon, this column is 0 (default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('coupon_allocation_minor')->default(0)->after('line_total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('coupon_allocation_minor');
        });
    }
};
