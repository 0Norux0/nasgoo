<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('discount_type', 20);   // percentage | fixed_amount
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('min_order_minor')->nullable();
            $table->unsignedInteger('max_discount_minor')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('KWD');
            $table->timestamps();
            $table->unique('code', 'coupons_code_unique');
            $table->index(['is_active', 'starts_at', 'ends_at'], 'coupons_active_window_idx');
            $table->index(['vendor_id', 'is_active'], 'coupons_vendor_active_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('coupons'); }
};
