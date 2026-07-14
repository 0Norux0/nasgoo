<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §8 — search_synonyms table.
 *
 * Admin-managed synonym dictionary that expands a user's search query into
 * a small candidate set during the relevance ranking pass.
 *
 * Example pairs (English): mobile↔phone, laptop↔notebook, tv↔television
 * Example pairs (Arabic):  جوال↔هاتف, تلفاز↔تلفزيون
 *
 * The synonym is bidirectional in semantics but stored as a single row
 * (term → synonym). The SynonymService union-joins both directions when
 * expanding so a single seed row covers both lookup directions.
 *
 * Cached at the application level (rebuilt on save via observer / settings
 * cache). Capacity expected to stay below ~500 active pairs in MVP.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();

            // Locale this pair applies to (en / ar / etc). Required; "all"
            // pairs should be explicitly seeded per-locale rather than via
            // a magic wildcard so admins can manage them per-language.
            $table->string('locale', 8);

            // Canonical term (left side). Stored case-normalized.
            $table->string('term', 80);

            // Synonym (right side). Stored case-normalized.
            $table->string('synonym', 80);

            $table->boolean('is_active')->default(true);

            // Optional foreign key — audit who created it. NULLable so the
            // record survives an admin user being deleted.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // No duplicate active pair within a locale. Case sensitivity
            // is handled at INSERT (we lowercase before storing).
            $table->unique(['locale', 'term', 'synonym'], 'search_synonyms_locale_pair_unique');

            // Index for the common lookup: WHERE locale=? AND is_active=1
            // ordered by term — covers the "expand this term" use case.
            $table->index(['locale', 'is_active', 'term'], 'search_synonyms_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
