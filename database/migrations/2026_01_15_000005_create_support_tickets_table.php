<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('number', 20)->unique();
            $table->string('ticket_type', 30);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('service_bookings')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('subject', 200);
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'tickets_user_status_idx');
            $table->index(['vendor_id', 'status'], 'tickets_vendor_status_idx');
            $table->index(['status', 'priority'], 'tickets_status_priority_idx');
            $table->index('assigned_to', 'tickets_assigned_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('support_tickets'); }
};
