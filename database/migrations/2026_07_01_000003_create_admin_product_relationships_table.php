<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.2 §15 — admin-curated product relationships.
 *
 * Lets administrators override or supplement algorithmic recommendations:
 *
 *   'pinned'        — always show this product as a recommendation for the
 *                     source, ranked above algorithmic results
 *   'hidden'        — never show this product as a recommendation for the source
 *   'complementary' — explicit accessory/companion relationship (laptop → bag),
 *                     used as FBT fallback when actual co-occurrence is thin
 *   'excluded'      — explicitly excluded pair (never co-recommend either direction)
 *
 * `reciprocal`: when true, the relationship applies in both directions
 * (A↔B). When false, only product_id → related_product_id is affected.
 *
 * Per dev §15:
 *   - changes are audited (created_by + timestamps)
 *   - no duplicate relationships (unique constraint)
 *   - exclusions are respected by the recommendation services
 *   - manual overrides are transparent (separate table from algorithmic data)
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('admin_product_relationships')) {
            return;
        }
        Schema::create('admin_product_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');
            $table->string('relationship_type', 24);    // pinned | hidden | complementary | excluded
            $table->boolean('reciprocal')->default(false);
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['product_id', 'related_product_id', 'relationship_type'],
                'admin_rel_unique'
            );
            $table->index(['product_id', 'relationship_type'], 'admin_rel_read_idx');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_product_relationships');
    }
};
