<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('discount_minor')->default(0);
            $table->string('currency', 3)->default('KWD');
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
            $table->index(['coupon_id', 'user_id'], 'cu_coupon_user_idx');
            $table->index('user_id', 'cu_user_idx');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('items_count')->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('discount_minor')->default(0)->after('coupon_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('total_minor')->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('coupon_discount_minor')->default(0)->after('coupon_id');
            $table->string('coupon_code', 50)->nullable()->after('coupon_discount_minor');
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_discount_minor', 'coupon_code']);
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_minor');
        });
        Schema::dropIfExists('coupon_usages');
    }
};
