<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Product.type now accepts 'service'.
 *
 * The products.type column is a `string` (not an enum) per Phase 3
 * design, so no schema change is required to add a new value. This
 * migration is a no-op that exists solely to document the additional
 * type and keep the Phase 8 migration timeline contiguous for future
 * reads. Constants live in App\Models\Product::TYPE_SERVICE.
 *
 * Existing types (Phase 3 → 7):
 *   simple, variable, digital, dropship, custom
 * Phase 8 adds:
 *   service
 *
 * If a downstream Phase 9+ needs to convert products.type to an enum,
 * it should ALTER the column and include all six values.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentional no-op — documented above.
        // Schema::table('products', function (Blueprint $table) { ... });
    }

    public function down(): void
    {
        // Intentional no-op — see up().
    }
};
