<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Service bookings.
 *
 * Standalone resource. A booking is the customer's appointment slot
 * with a specific provider for a specific service. Optional order_id
 * links to a Phase 4 Order if payment is required — free bookings have
 * order_id = NULL.
 *
 * Status state machine:
 *
 *   pending           ← initial state for "pay later" / free bookings
 *   pending_payment   ← initial state when an Order is created
 *   confirmed         ← payment captured OR vendor accepted free booking
 *   accepted          ← vendor reviewed and confirmed (separate from
 *                       payment confirmation; lets vendors gate-keep
 *                       even for paid bookings)
 *   rejected          ← terminal; refund flow if order_id present
 *   rescheduled       ← booked_for_date / booked_for_time updated; the
 *                       previous slot is freed
 *   cancelled         ← terminal; customer-initiated cancellation
 *   completed         ← terminal; service was delivered
 *   no_show           ← terminal; customer didn't show up
 *   refunded          ← terminal; payment refunded
 *
 * Double-booking prevention: the application layer holds a row-level
 * lock on (service_provider_id, booked_for_date) when reading active
 * bookings + counting them against max_bookings_per_slot. See
 * ServiceBookingService::createBooking().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();              // SVC-YYYYMMDD-XXXX

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // customer
            $table->foreignId('vendor_id')->constrained('vendors');                  // vendor
            $table->foreignId('product_id')->constrained('products');                // the service
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers'); // chosen staff

            // Optional link to a paid Order. NULL = free booking / pay-later.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->date('booked_for_date');
            $table->time('booked_for_time');
            $table->unsignedSmallInteger('duration_minutes');    // snapshot at booking time
            $table->string('location_mode', 32);                 // snapshot

            // Snapshot of price + currency. If order_id is set, the order
            // is authoritative for payment, but this column lets us render
            // the booking sheet without joining orders.
            $table->unsignedInteger('price_minor');
            $table->string('currency', 8);

            // For home-visit bookings: customer-supplied address as JSON.
            // (Same Gulf-style fields as Phase 1 Addresses table.)
            $table->json('service_address')->nullable();

            $table->string('status', 32);                        // see state machine above

            $table->text('customer_notes')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Phase 8 v8.2 — explicit short index names. The original
            // 3-column index would auto-generate to 74 chars, exceeding
            // MySQL's 64-char limit:
            //
            //   auto: service_bookings_service_provider_id_booked_for_date_booked_for_time_index  (74)
            //   v8.2: sb_provider_date_time_idx                                                    (25)
            //
            // The other two compound indexes are under 64 chars but
            // given explicit names here for consistency and so they
            // can be referenced by name if dropped in a future migration.
            // 'sb_' = service_bookings.
            $table->index(['service_provider_id', 'booked_for_date', 'booked_for_time'], 'sb_provider_date_time_idx');
            $table->index(['vendor_id', 'status', 'booked_for_date'], 'sb_vendor_status_date_idx');
            $table->index(['user_id', 'status'], 'sb_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bookings');
    }
};
