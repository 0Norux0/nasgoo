<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.3 §21 §24 — personalization_preferences.
 *
 * Per-user privacy settings. One row per user, created lazily when the user
 * first visits /account/personalization or acts on an opt-out control.
 *
 * Absence of a row = "use config defaults". Do NOT interpret NULL/absent as
 * "opted out" — that would surprise users and would require row-writing
 * on every user creation.
 *
 * Feedback rows (product-hide, category-hide) live in
 * `personalization_feedback` (separate table) so they can be pruned
 * independently and joined efficiently.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('personalization_preferences')) {
            Schema::create('personalization_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                // Master opt-out. When TRUE, all behavioral ranking bypassed;
                // generic marketplace sections shown instead.
                $table->boolean('behavioral_personalization_enabled')->default(true);
                // Merge guest activity on login (dev §20)
                $table->boolean('guest_merge_enabled')->default(true);
                // Behavior-tracking toggle (dev §21). When false, no new
                // customer_product_views rows are inserted for this user.
                $table->boolean('behavior_tracking_enabled')->default(true);
                $table->timestamp('last_reset_at')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('personalization_feedback')) {
            Schema::create('personalization_feedback', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('session_key', 64)->nullable();
                // 'not_interested' | 'hide_product' | 'show_fewer_like'
                $table->string('feedback_type', 32);
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

                // Fast "which products should be hidden for this user/session?"
                $table->index(['user_id', 'feedback_type', 'expires_at'], 'pf_user_type_idx');
                $table->index(['session_key', 'feedback_type', 'expires_at'], 'pf_session_type_idx');
                // Pruning
                $table->index('expires_at', 'pf_expires_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personalization_feedback');
        Schema::dropIfExists('personalization_preferences');
    }
};
