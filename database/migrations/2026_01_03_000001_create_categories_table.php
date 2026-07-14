<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('slug')->unique();
            $table->string('name');                      // canonical English name
            $table->json('name_translations')->nullable(); // {"ar": "...", "ur": "..."}
            $table->text('description')->nullable();

            // Media
            $table->string('icon_path')->nullable();
            $table->string('image_path')->nullable();

            // Tree bookkeeping — computed in CategoryTreeService
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('path')->nullable();          // "electronics/phones/smartphones"

            // Ordering / state
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            // Denormalised counter
            $table->unsignedInteger('products_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'position']);
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
