<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Services Marketplace foundation.
 *
 * service_details holds the service-specific fields for a Product where
 * products.type = 'service'. One-to-one with the parent Product. Keeps
 * the core products table clean and lets future service-only features
 * (multi-staff, multi-location) extend here without touching products.
 *
 * NOT NULL columns: product_id, service_type, location_mode, duration_minutes.
 * Defaults: is_active = true. (The customer-visible "active" flag is
 * Product::status; service_details.is_active is a service-listing-level
 * toggle the vendor uses when temporarily pausing bookings without
 * archiving the entire product.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();

            // Phase 8 service_type values — kept as string for forward-compat;
            // ServiceDetail::TYPE_* constants are the source of truth.
            $table->string('service_type', 32);     // appointment | home_visit | online | consultation | fixed_price
            $table->string('location_mode', 32);    // customer_location | provider_location | online | flexible

            $table->unsignedSmallInteger('duration_minutes')->default(60);

            // Comma-separated city/area whitelist (e.g. "Kuwait City, Salmiya"),
            // OR null for "service available everywhere". For online services
            // this is ignored.
            $table->string('service_area_text', 500)->nullable();

            // Default lead time the customer must book in advance, in minutes
            // (eg. 60 = "must book at least 1 hour ahead"). Avoids bookings
            // arriving inside the active provider work-day.
            $table->unsignedSmallInteger('min_lead_time_minutes')->default(0);

            // How far out the customer can book, in days. 30 = month-ahead booking.
            $table->unsignedSmallInteger('max_advance_days')->default(30);

            // Whether the customer can pick a specific provider, OR vendor
            // assigns one at confirmation. Some businesses (clinics) want
            // customer choice; others (cleaning crews) auto-assign.
            $table->boolean('allow_customer_provider_pick')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_details');
    }
};
