<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Weekly availability per provider.
 *
 * One row per (provider, day_of_week 0..6). Each row defines the working
 * window for that weekday. Bookings are slotted in slot_duration_minutes
 * intervals between start_time and end_time, excluding the optional break
 * window. max_bookings_per_slot supports multi-room / multi-chair vendors
 * who can accept more than one booking at the same time.
 *
 * Lunch / prayer break: a single optional contiguous window. For multiple
 * breaks per day (Friday prayer + lunch break), add a follow-up
 * service_availability_breaks table in a later iteration — out of scope
 * for the Phase 8 foundation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();

            // 0 = Sunday, 6 = Saturday (Laravel's Carbon convention).
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');                          // eg. 10:00:00
            $table->time('end_time');                            // eg. 20:00:00
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->unsignedSmallInteger('max_bookings_per_slot')->default(1);

            $table->time('break_start_time')->nullable();        // eg. 13:00:00
            $table->time('break_end_time')->nullable();          // eg. 14:00:00

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Phase 8 v8.2 — explicit short index name. The auto-generated
            // name would be 61 chars, only 3 below MySQL's 64-char limit
            // and exactly at Postgres' 63-char silent-truncation point.
            // Renaming defensively to avoid any future edge case (eg. if
            // a contributor adds a third column to the unique constraint).
            //
            //   auto: service_availabilities_service_provider_id_day_of_week_unique  (61)
            //   v8.2: sa_provider_dow_unique                                          (22)
            //
            // 'sa_' = service_availabilities. dow = day_of_week.
            $table->unique(['service_provider_id', 'day_of_week'], 'sa_provider_dow_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availabilities');
    }
};
