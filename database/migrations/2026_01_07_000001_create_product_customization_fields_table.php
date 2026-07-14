<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — product_customization_fields.
 *
 * Per-product customization field definitions. Vendor builds the form;
 * customer fills it in on the product detail page. Each field is one row
 * (NOT a JSON blob) so we get clean querying, deterministic validation
 * order, and the ability to add/edit/disable fields without rewriting
 * historical cart/order rows.
 *
 * `key` is a snake_case slug unique per product, used as the form-field
 * name and as the snapshot identifier on cart_item_customizations and
 * order_item_customizations.
 *
 * Field types (`type` column):
 *   image     — file upload (image)
 *   text      — single-line text
 *   textarea  — multi-line text / instructions
 *   color     — color selection (options=[{value,label,swatch}])
 *   font      — font selection (options=[{value,label}])
 *   placement — placement option (options=[{value,label}])
 *   dropdown  — generic dropdown (options=[{value,label}])
 *   size      — size selection (options=[{value,label}])
 *   checkbox  — single boolean checkbox
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_customization_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->json('label_translations')->nullable();
            $table->string('type', 32);
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            // File-type fields
            $table->json('allowed_file_types')->nullable();   // ['jpg','png','webp','pdf','svg']
            $table->unsignedInteger('max_file_size_kb')->nullable();

            // Text fields
            $table->unsignedInteger('max_text_length')->nullable();

            // Selection fields (color/font/placement/dropdown/size)
            $table->json('options')->nullable();              // [{value, label, swatch?, extra_fee?}]

            $table->unsignedInteger('extra_fee_minor')->default(0);
            $table->string('placeholder')->nullable();
            $table->text('helper_text')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'key']);
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_customization_fields');
    }
};
