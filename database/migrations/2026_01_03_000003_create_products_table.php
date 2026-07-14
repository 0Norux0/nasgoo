<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Identity
            $table->string('sku')->nullable();           // vendor-scoped SKU
            $table->string('slug')->unique();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->json('description_translations')->nullable();

            // Type
            $table->string('type')->default('simple');   // simple | variable | digital
            // (customizable / dropship / print_on_demand reserved for later phases)

            // Lifecycle
            $table->string('status')->default('draft');
            // draft | pending_review | published | rejected | archived
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();

            // Pricing — for simple products. Variable products override per variant.
            $table->unsignedInteger('price_minor')->default(0);
            $table->unsignedInteger('compare_at_price_minor')->nullable();
            $table->unsignedInteger('cost_price_minor')->nullable();
            $table->string('currency', 3)->default('KWD');

            // Inventory — simple products. Variable products track per variant.
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);

            // Shipping (used by Phase 4)
            $table->unsignedInteger('weight_grams')->nullable();

            // Storefront
            $table->boolean('featured')->default(false);
            $table->timestamp('featured_until')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Denormalised counters (updated by events in later phases)
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'published_at']);
            $table->index(['featured', 'featured_until']);
            $table->unique(['vendor_id', 'sku']);
        });

        // Many-to-many: products can sit in additional categories
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['product_id', 'category_id']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};
