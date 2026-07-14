<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — shipping zones + methods foundation.
 *
 * Zones map (country, optional state/region) tuples to a set of shipping
 * methods. Each method has a type:
 *   - flat_rate    — fixed fee_minor regardless of cart
 *   - free         — fee 0; optional min_subtotal_minor threshold
 *   - pickup       — fee 0; customer picks up from vendor
 *
 * Checkout integration: the existing orders.shipping_minor column gets the
 * resolved fee; orders.shipping_method_id (added here) records which method
 * the customer chose for traceability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->json('countries');     // array of ISO 3166-1 alpha-2 codes ("KW","AE",...)
            $table->json('regions')->nullable(); // optional: ["Kuwait City","Salmiya"]; null = whole-country
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('type', 20); // flat_rate | free | pickup
            $table->unsignedInteger('fee_minor')->default(0);
            $table->string('currency', 3)->default('KWD');
            $table->unsignedInteger('min_subtotal_minor')->nullable(); // free-over-threshold
            $table->unsignedInteger('max_weight_grams')->nullable();   // method weight cap
            $table->string('eta_label', 120)->nullable();              // "2-3 business days"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['shipping_zone_id', 'is_active', 'position']);
            $table->index('type');
            $table->unique(['shipping_zone_id', 'slug'], 'shipping_methods_zone_slug_unique');
        });

        // Link orders to the chosen method
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->after('currency')->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_method_name', 120)->nullable()->after('shipping_method_id'); // snapshot
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_name');
        });
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
