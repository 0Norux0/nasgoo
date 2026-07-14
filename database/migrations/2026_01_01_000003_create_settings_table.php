<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index();              // general / marketplace / currency / payment / shipping / commission / email / seo / social / security
            $table->string('key');
            $table->jsonb('value')->nullable();             // mixed type, JSON-encoded
            $table->string('type')->default('string');      // string / integer / boolean / json / array / encrypted
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false);   // can frontend read?
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
