<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 v11B.1.1 §3 — add short_description_translations to products.
 *
 * The products table from Phase 3 already has BOTH:
 *   - name_translations (json, nullable)
 *   - description_translations (json, nullable)
 *
 * but it does NOT have short_description_translations. v11B.1.1 adds it
 * for parity and so the product card / catalog can show localized short
 * descriptions when Arabic is selected.
 *
 * Strictly additive, nullable, reversible. No existing data is modified.
 * Slugs, prices, vendor_id, category_id, status — all untouched.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }
        // Idempotent: only add if missing (allows re-running on partially-migrated DBs)
        if (! Schema::hasColumn('products', 'short_description_translations')) {
            Schema::table('products', function (Blueprint $table) {
                // Place after short_description to keep related columns adjacent
                // (cosmetic — does not affect functionality).
                $table->json('short_description_translations')->nullable()->after('short_description');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }
        if (Schema::hasColumn('products', 'short_description_translations')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('short_description_translations');
            });
        }
    }
};
