<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — product reviews + ratings.
 *
 * Customers can review only products they've actually purchased and the
 * order has been delivered. Reviews start in `pending` and require admin
 * moderation before they appear on the product page. Approved reviews
 * roll up to `products.rating_avg` + `rating_count` (existing columns
 * since Phase 3).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->boolean('is_verified_purchase')->default(false);
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            // A customer can leave at most one review per product (per purchase).
            // The unique covers user_id+product_id+order_item_id so a customer
            // who buys the same product twice can review each purchase once.
            $table->unique(['user_id', 'product_id', 'order_item_id'], 'reviews_user_product_orderitem_unique');
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
