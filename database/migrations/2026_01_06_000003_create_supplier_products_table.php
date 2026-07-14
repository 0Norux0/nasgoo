<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — supplier_products. A raw product entry imported from a supplier
 * (manual entry / CSV row / API response). Lives separately from the
 * marketplace `products` table until a vendor maps it through the review
 * workflow. Once mapped, the marketplace Product gets `supplier_product_id`
 * pointing back here.
 *
 * Status transitions:
 *   pending → mapped → published    (happy path, includes admin approval)
 *   pending → rejected              (vendor or admin discards it)
 *   any    → discontinued           (supplier removes the product)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('supplier_platform_id')->constrained('supplier_platforms')->cascadeOnDelete();
            $table->foreignId('supplier_integration_id')->nullable()->constrained('supplier_integrations')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // set when mapped

            $table->string('external_product_id')->nullable();       // supplier's product ID
            $table->string('external_sku')->nullable();
            $table->string('source_url', 1024)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('images')->nullable();                       // array of image URLs

            $table->unsignedInteger('supplier_cost_minor')->default(0);
            $table->string('supplier_currency', 3)->default('USD');
            $table->string('supplier_stock_status')->default('unknown'); // in_stock | out_of_stock | unknown
            $table->unsignedInteger('supplier_stock_qty')->nullable();
            $table->unsignedInteger('supplier_shipping_minor')->default(0);
            $table->unsignedSmallInteger('estimated_delivery_days')->nullable();

            $table->json('raw_payload')->nullable();                  // original CSV row / API response for audit

            $table->string('import_status')->default('pending');      // pending | mapped | published | rejected | discontinued
            $table->text('import_notes')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'import_status']);
            $table->index(['supplier_platform_id', 'external_product_id']);
            $table->index('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
