<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Globally configured payment options. The `provider` column maps to
        // a PHP class implementing PaymentProvider; the `config` JSON holds
        // provider-specific tunables (e.g. min order, fee structure).
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();             // 'cod', 'manual_transfer', 'online_mock'
            $table->string('provider');                   // matches PaymentProvider::name()
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();
            $table->json('description_translations')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            // Whether the provider can be selected at checkout (some methods
            // might be admin-set-only — e.g. wire transfer for B2B)
            $table->boolean('available_at_checkout')->default(true);

            $table->json('config')->nullable();           // provider-specific
            $table->json('supported_currencies')->nullable(); // null = all

            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            // Slug copied at payment time — survives method deletion
            $table->string('method_slug');
            $table->string('provider');

            $table->string('status')->default('pending');
            // pending | authorized | captured | failed | refunded | partially_refunded | cancelled

            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3);
            $table->unsignedInteger('refunded_minor')->default(0);

            // Provider's reference for reconciliation — internal/external IDs
            $table->string('external_id')->nullable()->index();
            $table->string('reference')->nullable();           // human-readable for staff

            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // Append-only audit log of payment movements. Critical for reconciliation.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('type');             // authorize | capture | refund | void | webhook
            $table->string('status');           // succeeded | failed | pending
            $table->integer('amount_minor');    // signed: refunds are positive amounts (direction encoded in type)
            $table->string('currency', 3);
            $table->string('external_id')->nullable();
            $table->json('payload')->nullable(); // raw provider response for debugging
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
