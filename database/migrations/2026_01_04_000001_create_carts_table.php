<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('KWD');
            // Denormalised totals so the cart widget doesn't re-aggregate every paint.
            // Recomputed by CartService on every mutation.
            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamps();

            $table->unique('user_id'); // one active cart per user
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_minor');  // snapshot at add-time
            $table->string('currency', 3);
            // Snapshot of vendor for grouping/limits even if product changes vendor later
            $table->foreignId('vendor_id')->constrained();

            $table->timestamps();

            // Same product+variant collapses to one row (qty increments) — see CartService
            $table->unique(['cart_id', 'product_id', 'variant_id'], 'cart_items_unique_line');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
