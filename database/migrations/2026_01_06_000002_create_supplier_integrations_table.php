<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — supplier_integrations. A vendor's specific configuration to talk
 * to a supplier platform: which integration type they're using, optional
 * credentials (stored encrypted via Eloquent's 'encrypted' cast), and the
 * last-sync state.
 *
 * `credentials` is a JSONB blob encrypted at the application layer. We never
 * expose raw API keys to the UI; the Filament resource lets vendors paste new
 * values but only shows masked/last-4 read-back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('supplier_platform_id')->constrained('supplier_platforms')->cascadeOnDelete();
            $table->string('name');                                  // "My Alibaba 2026 catalogue"
            $table->string('integration_type');                      // manual | csv | api | feed
            $table->text('credentials')->nullable();                 // encrypted JSON (api key, secret, etc.)
            $table->string('feed_url')->nullable();                  // CSV/XML feed URL if any
            $table->json('sync_options')->nullable();                // freeform JSON for future sync prefs
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();          // success | failure | partial
            $table->text('last_sync_message')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'supplier_platform_id', 'name']);
            $table->index(['vendor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_integrations');
    }
};
