<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_commission_rules', function (Blueprint $table) {
            $table->id();

            // Optional vendor binding — null = applies more broadly via scope
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();

            // Scope of the rule
            // global | vendor | package | category | product | service_category | service
            $table->string('scope')->default('vendor');
            $table->unsignedBigInteger('scope_id')->nullable(); // e.g. package_id, category_id

            // Product/service segmentation
            $table->string('product_type')->default('any');
            // any | simple | variable | customizable | dropship | print_on_demand | service

            $table->string('payment_method')->default('any');
            // any | online | cod | wallet

            // Calculation basis
            $table->string('calculation_base')->default('selling_price');
            // selling_price | net_profit_after_cost | subtotal_before_shipping
            // | subtotal_after_discount | service_fee | booking_amount | promotion_fee

            // Commission type
            $table->string('commission_type')->default('percent'); // percent | fixed | fixed_plus_percent
            $table->decimal('percent_value', 7, 4)->nullable();
            $table->unsignedInteger('fixed_value_minor')->nullable();
            $table->string('currency', 3)->default('KWD');

            // Resolution
            $table->unsignedInteger('priority')->default(100); // lower wins
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['scope', 'scope_id', 'is_active']);
            $table->index(['vendor_id', 'priority']);
            $table->index(['effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_commission_rules');
    }
};
