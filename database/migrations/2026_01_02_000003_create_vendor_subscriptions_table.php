<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_package_id')->constrained()->restrictOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable(); // null = lifetime

            $table->string('status')->default('active'); // active | expired | cancelled | grace | pending

            $table->boolean('auto_renew')->default(false);

            // Snapshot of what was actually paid
            $table->unsignedInteger('amount_paid_minor')->default(0);
            $table->string('currency', 3)->default('KWD');
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');
    }
};
