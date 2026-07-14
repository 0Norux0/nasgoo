<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 v10.1 — performance indexes
 *
 * Targets the queries we know are hottest in production:
 *
 *   ReportsService::adminFinancialSummary
 *     SELECT ... FROM orders WHERE created_at BETWEEN ? AND ?
 *                                 AND status NOT IN ('cancelled')
 *
 *   ReportsService::topVendorsByGross / vendorFinancialSummary
 *     SELECT ... FROM order_items
 *      JOIN orders ON orders.id = order_items.order_id
 *      WHERE order_items.vendor_id = ?
 *        AND orders.created_at BETWEEN ? AND ?
 *
 *   ReportsService::topProductsByUnits
 *     SELECT ... FROM order_items
 *      WHERE product_id IS NOT NULL
 *      GROUP BY product_id
 *
 *   CatalogController::index
 *     SELECT ... FROM products WHERE status = 'published'
 *                                  AND type != 'service'
 *                                  AND category_id = ?
 *
 *   SitemapController
 *     SELECT slug, updated_at FROM products WHERE status = 'published'
 *                                              AND type = 'service'/'!='
 *
 * MySQL identifier limit is 64 chars — every index name below stays well
 * under (the v8.2 defense). Idempotent: checks via hasIndex() before
 * adding so re-running the migration is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Reports use (created_at, status) constantly
            if (! $this->hasIndex('orders', 'orders_created_status_idx')) {
                $table->index(['created_at', 'status'], 'orders_created_status_idx');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            // vendorFinancialSummary + topVendorsByGross filter by vendor_id then JOIN orders
            if (! $this->hasIndex('order_items', 'order_items_vendor_order_idx')) {
                $table->index(['vendor_id', 'order_id'], 'order_items_vendor_order_idx');
            }
            // topProductsByUnits groups by product_id
            if (! $this->hasIndex('order_items', 'order_items_product_idx')) {
                $table->index('product_id', 'order_items_product_idx');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            // Catalog + sitemap filter by (status, type) primarily
            if (! $this->hasIndex('products', 'products_status_type_idx')) {
                $table->index(['status', 'type'], 'products_status_type_idx');
            }
            // Catalog category filter
            if (! $this->hasIndex('products', 'products_category_status_idx')) {
                $table->index(['category_id', 'status'], 'products_category_status_idx');
            }
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            // approvedReviews relation: status='approved' + product_id
            if (! $this->hasIndex('product_reviews', 'product_reviews_prod_status_idx')) {
                $table->index(['product_id', 'status'], 'product_reviews_prod_status_idx');
            }
        });

        Schema::table('vendor_payout_requests', function (Blueprint $table) {
            // adminPayoutSummary + vendorFinancialSummary filter by (created_at, status)
            if (! $this->hasIndex('vendor_payout_requests', 'vpr_created_status_idx')) {
                $table->index(['created_at', 'status'], 'vpr_created_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders', 'orders_created_status_idx')) {
                $table->dropIndex('orders_created_status_idx');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if ($this->hasIndex('order_items', 'order_items_vendor_order_idx')) {
                $table->dropIndex('order_items_vendor_order_idx');
            }
            if ($this->hasIndex('order_items', 'order_items_product_idx')) {
                $table->dropIndex('order_items_product_idx');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if ($this->hasIndex('products', 'products_status_type_idx')) {
                $table->dropIndex('products_status_type_idx');
            }
            if ($this->hasIndex('products', 'products_category_status_idx')) {
                $table->dropIndex('products_category_status_idx');
            }
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            if ($this->hasIndex('product_reviews', 'product_reviews_prod_status_idx')) {
                $table->dropIndex('product_reviews_prod_status_idx');
            }
        });

        Schema::table('vendor_payout_requests', function (Blueprint $table) {
            if ($this->hasIndex('vendor_payout_requests', 'vpr_created_status_idx')) {
                $table->dropIndex('vpr_created_status_idx');
            }
        });
    }

    /**
     * Portable index-existence check. doctrine/dbal isn't required in
     * Laravel 11; we use the SchemaManager via PDO.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $idx = \Illuminate\Support\Facades\Schema::getConnection()
                ->getSchemaBuilder()
                ->getIndexes($table);
            foreach ($idx as $i) {
                if (($i['name'] ?? null) === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // older Laravel or DB driver without getIndexes — assume missing
        }
        return false;
    }
};
