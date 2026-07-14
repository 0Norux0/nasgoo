<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Pivot: which providers can deliver which services.
 *
 * (service_provider_id, product_id) is unique — a provider can't be
 * "assigned twice" to the same service. The cross-vendor invariant
 * (provider's vendor === product's vendor) is enforced at the
 * application layer in VendorServiceProviderController so we get clean
 * validation errors instead of FK violations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_provider_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            // Phase 8 v8.2 — explicit short index names. Laravel's auto-
            // generated names would be 66 chars (unique) and 65 chars
            // (compound index), exceeding MySQL's 64-char identifier
            // limit. Postgres silently truncates so CI was passing, but
            // MySQL hard-errors on migrate:fresh.
            //
            //   auto: service_provider_assignments_service_provider_id_product_id_unique  (66)
            //   v8.2: spa_provider_product_unique                                          (27)
            //
            //   auto: service_provider_assignments_product_id_service_provider_id_index   (65)
            //   v8.2: spa_product_provider_idx                                              (24)
            //
            // 'spa_' = service_provider_assignments. All Phase 8 explicit
            // index names use a 3-letter table prefix for consistency.
            $table->unique(['service_provider_id', 'product_id'], 'spa_provider_product_unique');
            $table->index(['product_id', 'service_provider_id'], 'spa_product_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_provider_assignments');
    }
};
