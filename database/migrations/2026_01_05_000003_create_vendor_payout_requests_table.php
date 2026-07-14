<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — vendor payout requests.
 *
 * Vendor's "wallet" is computed live from order_items.vendor_earning_minor:
 *   - lifetime_earnings: sum on items where order.payment_status='paid'
 *   - pending_balance:   delivered but earnings_release_at > now()
 *   - available_balance: delivered AND earnings_release_at <= now()
 *                        MINUS any pending/approved payout requests
 *                        MINUS any paid payout amounts already disbursed
 *
 * A payout request reserves a portion of `available_balance` while it's
 * pending/approved; once `paid`, the amount is permanently deducted.
 *
 * Status flow: pending → approved → paid
 *                     └─ rejected (terminal)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedInteger('requested_amount_minor');
            $table->string('currency', 3)->default('KWD');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | paid
            $table->string('payout_method', 40)->default('bank_transfer'); // bank_transfer | other
            $table->json('payout_details')->nullable(); // bank name/iban/etc. — vendor-provided
            $table->string('admin_notes', 500)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('transfer_reference', 120)->nullable(); // e.g. SWIFT, bank ref
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index('status');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_requests');
    }
};
