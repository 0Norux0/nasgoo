<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Blocked dates (one-off vacation / national holiday / sick day).
 *
 * Overrides the weekly availability — if a row exists for (provider, date)
 * the provider is unavailable that day regardless of weekly schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['service_provider_id', 'date']);
            $table->index(['date', 'service_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_blocked_dates');
    }
};
