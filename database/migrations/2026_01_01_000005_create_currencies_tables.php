<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();          // ISO 4217: KWD, USD, AED, PKR
            $table->string('name');                        // Kuwaiti Dinar, US Dollar...
            $table->string('symbol', 10);                  // KD, $, AED, ₨
            $table->unsignedTinyInteger('decimal_places')->default(2); // KWD=3, USD/AED/PKR=2
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);            // e.g. KWD
            $table->string('target_currency', 3);          // e.g. USD
            $table->decimal('rate', 20, 8);                 // 1 KWD = 3.25 USD
            $table->string('source')->default('manual');    // manual / api / fixer / ecb
            $table->timestamp('effective_at');               // when this rate becomes valid
            $table->timestamps();

            $table->index(['base_currency', 'target_currency', 'effective_at']);
            $table->foreign('base_currency')->references('code')->on('currencies')->cascadeOnDelete();
            $table->foreign('target_currency')->references('code')->on('currencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currencies');
    }
};
