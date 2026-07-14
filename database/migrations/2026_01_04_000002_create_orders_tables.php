<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();           // human-readable "MK-2026-00001"
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Composite state: payment + fulfillment + an overall lifecycle status
            $table->string('status')->default('pending_payment');
            // pending_payment | paid | confirmed | shipped | delivered |
            // completed | cancelled | refunded | failed
            $table->string('payment_status')->default('pending');
            // pending | authorized | paid | failed | refunded | partially_refunded
            $table->string('fulfillment_status')->default('unfulfilled');
            // unfulfilled | partially_fulfilled | fulfilled | returned

            // Money — minor units, ONE currency per order (no FX at order time)
            $table->string('currency', 3)->default('KWD');
            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('shipping_minor')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);          // 0 in Phase 4 — tax model is Phase 5+
            $table->integer('discount_minor')->default(0);              // signed: negative wouldn't make sense, but keep signed for safety
            $table->unsignedInteger('total_minor')->default(0);

            // Snapshot of platform/vendor split for fast read access
            $table->unsignedInteger('platform_commission_minor')->default(0);
            $table->unsignedInteger('vendor_earnings_minor')->default(0);

            // Customer-facing notes
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Lifecycle stamps — set by domain services, never by controllers
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Vendor earnings release — locked Phase 2 decision: 7-day hold after delivery
            $table->timestamp('earnings_release_at')->nullable();
            $table->boolean('earnings_released')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshots — order line stays valid even if the product is later deleted
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('variant_name')->nullable();
            $table->json('variant_attributes')->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_minor');
            $table->unsignedInteger('line_total_minor');
            $table->string('currency', 3);

            // Per-line commission snapshot — using CommissionResolver at order placement time
            $table->decimal('commission_percent', 5, 2)->default(0);   // e.g. 20.00 for 20%
            $table->unsignedInteger('commission_amount_minor')->default(0);
            $table->unsignedInteger('vendor_earning_minor')->default(0);

            // Per-line fulfillment status — supports partial shipments
            $table->string('fulfillment_status')->default('unfulfilled');

            $table->timestamps();

            $table->index(['vendor_id', 'fulfillment_status']);
            $table->index(['order_id', 'vendor_id']);
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');   // 'billing' | 'shipping'

            // Snapshot — column shape mirrors the Phase 1 `addresses` table
            // exactly so any address from the picker round-trips faithfully.
            // (v5.2 fix: pre-v5.2 columns full_name / line1 / line2 / region
            // were a phantom Western schema that didn't match the real
            // addresses table — see PHASE_4_v5.2_PATCH_NOTES.md.)
            $table->string('recipient_name');               // who the package is for (defaults to user.name)
            $table->string('phone')->nullable();
            $table->string('country', 2);                   // ISO 3166-1 alpha-2
            $table->string('state')->nullable();            // governorate / state
            $table->string('city');
            $table->string('area')->nullable();
            $table->string('block')->nullable();
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // placed, paid, confirmed, shipped, delivered, cancelled, refunded, note
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable(); // 'system' | 'customer' | 'vendor' | 'admin'
            $table->timestamps();

            $table->index(['order_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
