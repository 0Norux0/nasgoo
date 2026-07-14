<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — supplier_platforms. Admin-managed list of dropshipping/wholesale
 * platforms vendors can import products from (Amazon, Temu, Daraz, AliExpress,
 * etc.) The platform record only stores administrative metadata; actual
 * vendor-specific integration credentials live in supplier_integrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                   // "AliExpress"
            $table->string('slug')->unique();                         // "aliexpress"
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('integration_type')->default('manual');    // manual | csv | api | feed
            $table->string('default_currency', 3)->default('USD');
            $table->unsignedSmallInteger('default_delivery_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_platforms');
    }
};
