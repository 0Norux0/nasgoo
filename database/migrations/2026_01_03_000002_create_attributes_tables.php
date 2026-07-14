<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();             // 'color', 'size', 'material'
            $table->string('name');                       // 'Color'
            $table->json('name_translations')->nullable();
            $table->string('type')->default('select');    // select | text | number | boolean
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_variation')->default(false); // used to build product_variants
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('slug');                       // 'red', 'small'
            $table->string('value');                      // 'Red', 'Small'
            $table->json('value_translations')->nullable();
            $table->string('color_hex', 9)->nullable();   // for color swatches: "#FF0000"
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
