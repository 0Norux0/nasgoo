<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Service providers / staff.
 *
 * A vendor can have many providers (doctors at a clinic, beauticians at
 * a salon, technicians at a repair company). Each provider can be assigned
 * to many services via service_provider_assignments.
 *
 * (vendor_id, slug) is unique so each vendor's staff URLs don't clash
 * but two different vendors can independently have a "Dr. Smith".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');                              // unique per vendor

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('bio')->nullable();
            $table->string('specialization')->nullable();        // "Cardiology" / "Bridal makeup" / "AC repair"
            $table->string('qualification', 500)->nullable();    // "MBBS, MD" / "8 years experience"

            // Profile image path on the private disk. nullable — vendor can add later.
            $table->string('profile_image_path')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['vendor_id', 'slug']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
