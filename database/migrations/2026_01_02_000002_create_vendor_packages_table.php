<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Pricing — integer minor units (e.g. fils for KWD)
            $table->unsignedInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('KWD');
            $table->string('billing_cycle')->default('monthly'); // monthly | yearly | lifetime
            $table->unsignedSmallInteger('trial_days')->default(0);

            // Limits (null = unlimited)
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_services')->nullable();
            $table->unsignedSmallInteger('max_images_per_product')->default(5);

            // Feature flags
            $table->boolean('allow_video')->default(false);
            $table->boolean('allow_3d')->default(false);
            $table->boolean('allow_dropshipping')->default(false);
            $table->boolean('allow_product_import')->default(false);
            $table->boolean('allow_customization')->default(false);
            $table->boolean('allow_services')->default(false);
            $table->boolean('allow_promotions')->default(false);
            $table->boolean('allow_deal_of_day')->default(false);
            $table->boolean('allow_featured_vendor')->default(false);

            $table->string('analytics_level')->default('basic'); // basic | standard | advanced

            // Default commission for this package (overridable per vendor)
            $table->decimal('default_admin_commission_percent', 5, 2)->default(20.00);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_packages');
    }
};
