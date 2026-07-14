<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — cart_item_customizations.
 *
 * One row per filled customization field on a cart line. We snapshot
 * field_key/label/type from the source field so that even if the vendor
 * later edits or deactivates a field, the customer's in-cart selections
 * still display correctly.
 *
 * `file_path` is relative to the `local` (private) disk — never the
 * `public` disk. File access by anyone other than the owning user goes
 * through a signed URL and policy check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')->constrained('cart_items')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('product_customization_fields')->nullOnDelete();

            // Snapshots so render remains correct even if the source field changes/disappears
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

            $table->index('cart_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_customizations');
    }
};
