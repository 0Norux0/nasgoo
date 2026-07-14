<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12.3 — License activation tables.
 *
 * Additive only. No changes to existing tables. Safe to run on any
 * existing production database.
 *
 *   license_activations  — one row per valid activation (current + history)
 *   license_audit_logs   — every activation attempt, success or failure
 *
 * We deliberately do NOT store the raw license token — only a hash for
 * deduplication + the decoded payload. Storing the raw token would give
 * anyone with DB read access a way to reuse it elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();

            // SHA-256 of the raw token, hex-encoded (64 chars).
            // Used to dedupe activations of the same token.
            $table->string('token_hash', 64)->unique();

            // Decoded payload as JSON — human-readable copy of the signed claims
            $table->json('payload');

            // Denormalized claim fields for indexed queries
            $table->string('license_holder')->nullable();
            $table->string('license_type', 32)->default('standard');
            $table->string('domain')->nullable();
            $table->string('app_url')->nullable();
            $table->string('server_fingerprint', 64)->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->useCurrent();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();

            // Status: 'active' | 'expired' | 'revoked' | 'superseded'
            $table->string('status', 16)->default('active');

            $table->timestamp('last_checked_at')->nullable();

            // Extra data (activation IP, user-agent, etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at'], 'license_status_expires_idx');
            $table->index('activated_at');
        });

        Schema::create('license_audit_logs', function (Blueprint $table) {
            $table->id();

            // 'activation.success' | 'activation.failure' | 'expiry' |
            // 'grace_entered' | 'cache_cleared' | 'blocked_request'
            $table->string('event', 48);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('token_hash', 64)->nullable();
            $table->string('reason')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->json('context')->nullable();

            // Immutable log — created_at only
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at'], 'license_audit_event_created_idx');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_audit_logs');
        Schema::dropIfExists('license_activations');
    }
};
