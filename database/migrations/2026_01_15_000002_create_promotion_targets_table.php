<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('targetable_type', 50);
            $table->unsignedBigInteger('targetable_id');
            $table->timestamps();
            $table->unique(['promotion_id', 'targetable_type', 'targetable_id'], 'pt_unique');
            $table->index(['targetable_type', 'targetable_id'], 'pt_target_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('promotion_targets'); }
};
