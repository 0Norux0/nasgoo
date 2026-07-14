<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — supplier_orders + supplier_order_events.
 *
 * When a customer orders a dropshipping product, a supplier_order record is
 * created so the vendor/admin can manually mark it placed → shipped → delivered
 * with the supplier. One order_item maps to at most one supplier_order via
 * order_items.supplier_order_id (added in a separate migration).
 *
 * supplier_order_events captures status transitions for audit/timeline,
 * mirroring the order_events pattern in Phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('supplier_platform_id')->constrained('supplier_platforms')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();

            $table->string('number')->unique();                       // SUP-2026-NNNNN
            $table->string('status')->default('pending');             // pending | placed | packed | shipped | delivered | cancelled | failed | refunded
            $table->string('supplier_reference')->nullable();         // vendor pastes the supplier order ID
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 1024)->nullable();
            $table->string('carrier')->nullable();

            $table->unsignedInteger('supplier_cost_minor')->default(0);
            $table->unsignedInteger('supplier_shipping_minor')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('KWD');

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('supplier_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained('supplier_orders')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();                 // admin | vendor | system
            $table->string('event_type');                              // status.placed, status.shipped, etc.
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('supplier_order_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_events');
        Schema::dropIfExists('supplier_orders');
    }
};
