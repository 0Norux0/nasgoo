<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('promotion_type', 30);
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('min_order_minor')->nullable();
            $table->unsignedInteger('max_discount_minor')->nullable();
            $table->string('approval_status', 20)->default('approved');
            $table->text('rejection_reason')->nullable();
            $table->string('currency', 3)->default('KWD');
            $table->timestamps();
            // v8.2 — explicit short index names
            $table->index(['is_active', 'starts_at', 'ends_at'], 'prom_active_window_idx');
            $table->index(['vendor_id', 'is_active'], 'prom_vendor_active_idx');
            $table->index('approval_status', 'prom_approval_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('promotions'); }
};
