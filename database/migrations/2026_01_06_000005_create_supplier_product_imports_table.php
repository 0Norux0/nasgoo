<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — supplier_product_imports. One row per CSV upload batch so vendors
 * can see prior imports, what passed/failed validation, and download a
 * per-row error report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('supplier_integration_id')->nullable()->constrained('supplier_integrations')->nullOnDelete();
            $table->foreignId('supplier_platform_id')->constrained('supplier_platforms')->cascadeOnDelete();

            $table->string('original_filename')->nullable();
            $table->string('status')->default('processing');          // processing | completed | failed
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();                       // [{row: 4, message: "supplier_cost is required"}, ...]
            $table->json('summary')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_imports');
    }
};
