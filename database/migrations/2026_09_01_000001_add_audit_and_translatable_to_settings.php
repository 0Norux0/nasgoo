<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.3 v11B.3.1 §4 §13 — add audit columns to the existing settings table.
 *
 * Existing settings table (Phase 1) has `group + key + value + type + is_encrypted +
 * is_public + description`. This migration adds:
 *   - updated_by (nullable FK to users) — audit trail per dev §13 "audit history"
 *   - is_translatable (bool) — marker for settings whose value is a
 *     locale-keyed array { en: ..., ar: ... }
 *
 * Purely additive + idempotent (guarded).
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('settings')) return;
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('description');
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('settings', 'is_translatable')) {
                $table->boolean('is_translatable')->default(false)->after('is_public');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) return;
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('settings', 'is_translatable')) {
                $table->dropColumn('is_translatable');
            }
        });
    }
};
