<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 §23 — performance indexes for the smart-search query plan.
 *
 * Per dev §23: "Add only justified indexes. Use additive migrations. Avoid
 * duplicate indexes. Use MySQL-compatible index names. Consider index length
 * for text/varchar fields."
 *
 * Existing indexes on products (per Phase 3 migration):
 *   - (vendor_id, status)
 *   - (category_id, status)
 *   - (status, published_at)
 *   - (featured, featured_until)
 *
 * NEW indexes this migration adds (all additive, idempotent):
 *
 *   1. (status, rating_avg)         — for "Highest Rated" sort with status filter
 *   2. (status, sales_count)        — for "Best Selling" sort
 *   3. (status, views_count)        — for "Most Popular" sort
 *   4. (status, price_minor)        — for "Price: low/high" sort with status filter
 *      (Existing single-column index on price_minor may not exist; check.)
 *   5. Prefix index on `name` (first 64 chars) — for LOWER(name) LIKE 'prefix%'
 *      indexed prefix matching. MySQL InnoDB supports this; per §23 it's safe.
 *
 * Each index is wrapped in an existence check so this migration is
 * idempotent and safe to re-run.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            // Helper closure: only add an index if it doesn't already exist.
            $this->addIndexIfMissing($table, 'products', 'products_status_rating_idx',  ['status', 'rating_avg']);
            $this->addIndexIfMissing($table, 'products', 'products_status_sales_idx',   ['status', 'sales_count']);
            $this->addIndexIfMissing($table, 'products', 'products_status_views_idx',   ['status', 'views_count']);
            $this->addIndexIfMissing($table, 'products', 'products_status_price_idx',   ['status', 'price_minor']);
        });

        // Prefix index on `name` requires raw SQL (not directly supported via
        // Blueprint). Skip on SQLite (testing) where prefix indexes aren't a thing.
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $exists = collect(\DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_name_prefix_idx'"))->isNotEmpty();
                if (! $exists) {
                    \DB::statement("CREATE INDEX products_name_prefix_idx ON products (name(64))");
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — main indexes above already provide significant gain.
            \Log::warning('v11B.1 products name prefix index skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'products', 'products_status_rating_idx');
            $this->dropIndexIfExists($table, 'products', 'products_status_sales_idx');
            $this->dropIndexIfExists($table, 'products', 'products_status_views_idx');
            $this->dropIndexIfExists($table, 'products', 'products_status_price_idx');
        });

        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $exists = collect(\DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_name_prefix_idx'"))->isNotEmpty();
                if ($exists) {
                    \DB::statement("DROP INDEX products_name_prefix_idx ON products");
                }
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    private function addIndexIfMissing(Blueprint $table, string $tableName, string $indexName, array $columns): void
    {
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $exists = collect(\DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$indexName]))->isNotEmpty();
                if ($exists) {
                    return;
                }
            }
            // For SQLite / Postgres / other engines: rely on Blueprint's named-index pattern; idempotency
            // less critical in test envs which use migrate:fresh.
            $table->index($columns, $indexName);
        } catch (\Throwable $e) {
            \Log::warning("v11B.1 index {$indexName} skipped: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $exists = collect(\DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$indexName]))->isNotEmpty();
                if (! $exists) {
                    return;
                }
            }
            $table->dropIndex($indexName);
        } catch (\Throwable) {
            // ignore
        }
    }
};
