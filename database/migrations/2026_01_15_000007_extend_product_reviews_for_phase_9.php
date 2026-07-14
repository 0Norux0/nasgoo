<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->text('vendor_response')->nullable()->after('rejection_reason');
            $table->timestamp('vendor_responded_at')->nullable()->after('vendor_response');
            $table->json('images')->nullable()->after('vendor_responded_at');
        });
    }
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['vendor_response', 'vendor_responded_at', 'images']);
        });
    }
};
