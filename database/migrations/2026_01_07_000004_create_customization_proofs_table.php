<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — customization_proofs.
 *
 * Vendor uploads a proof for the customer to review. One order_item can
 * have multiple proofs (re-uploaded after a rejection round). The
 * `customer_response` text + `responded_at` capture the approval/rejection
 * narrative.
 *
 * Status lifecycle:
 *   draft     — vendor uploaded but not yet sent
 *   sent      — visible to the customer for review
 *   approved  — customer accepted
 *   rejected  — customer rejected; customer_response holds the reason
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customization_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->string('file_path');                          // private disk
            $table->string('file_original_name');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size_bytes');

            $table->string('status', 16)->default('draft');       // draft | sent | approved | rejected
            $table->text('vendor_note')->nullable();
            $table->text('customer_response')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['order_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customization_proofs');
    }
};
