<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — order_item_customizations.
 *
 * Permanent snapshot of customer customization data at checkout time.
 * Mirrors cart_item_customizations BUT does NOT keep a FK to the source
 * field — order data is immutable history; the field could be deleted
 * later without losing what the customer ordered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->string('field_key', 64);
            $table->string('field_label');
            $table->string('field_type', 32);

            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();

            $table->unsignedInteger('extra_fee_minor')->default(0);

            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_customizations');
    }
};
