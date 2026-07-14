<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->nullable();
            $table->string('name')->nullable();                 // auto: "Red / M"

            // Per-variant pricing override
            $table->unsignedInteger('price_minor')->default(0);
            $table->unsignedInteger('compare_at_price_minor')->nullable();
            $table->string('currency', 3)->default('KWD');

            $table->integer('stock')->default(0);

            // Denormalised attribute values for fast lookup at the storefront
            // e.g. {"color":"red","size":"m"}
            $table->json('attribute_values')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
            $table->unique(['product_id', 'sku']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        // Products link to attribute_values for spec-style attributes (e.g. brand=Sony)
        // and for the value set that variants are built from.
        Schema::create('product_attribute_value', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_value');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
    }
};
