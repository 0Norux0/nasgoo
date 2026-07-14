<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Business identity
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->string('business_email');
            $table->string('business_phone')->nullable();
            $table->string('business_type')->default('individual'); // individual | company
            $table->text('description')->nullable();

            // Owner (may differ from user; for B2B applications)
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_phone')->nullable();

            // Location
            $table->string('country', 2)->default('KW'); // ISO-2
            $table->string('city')->nullable();
            $table->text('address')->nullable();

            // Media / docs (path columns; files live on the 'vendors' disk)
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('license_document_path')->nullable();
            $table->string('id_document_path')->nullable();

            // Legal identifiers
            $table->string('commercial_license_no')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('civil_id')->nullable();

            // Payout
            $table->string('payout_method')->nullable(); // bank_transfer | wallet | other
            $table->text('payout_details')->nullable();  // encrypted JSON (see model cast)

            // Status workflow
            $table->string('status')->default('pending'); // pending | approved | rejected | suspended | closed
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();

            // Storefront promotion
            $table->boolean('featured')->default(false);
            $table->timestamp('featured_until')->nullable();

            // Stats (denormalised; updated by Phase 3+ events)
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['country', 'city']);
            $table->index('featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
