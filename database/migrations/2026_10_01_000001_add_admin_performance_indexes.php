<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.3 v11B.3.2 §4 §5 — additive indexes for admin dashboard perf.
 *
 * REASONING: the pre-v11B.3.2 admin dashboard ran ~23 uncached count
 * queries per render. Even after caching (5-min TTL), a cache miss must
 * still scan the tables. Without these indexes, each COUNT(CASE WHEN
 * status = X ...) forces a full table scan.
 *
 * INDEXES ADDED (justifications per dev §5):
 *
 *   users(status)  — StatsOverview counts users WHERE status='active'.
 *                    Table size grows unbounded; without an index, this
 *                    is a full scan on every dashboard render.
 *
 *   vendors(status) — same widget counts WHERE status IN (pending, approved).
 *                     There's typically a `status` filter in admin list
 *                     views too.
 *
 *   products(status) — same widget counts WHERE status IN (published,
 *                      pending_review). Storefront also filters by
 *                      status = published on virtually every browse.
 *
 *   orders(status, payment_status) — StatsOverview scans by both. Also
 *                                    used by admin order list filters.
 *
 *   categories(is_active, parent_id) — StatsOverview counts by both.
 *                                       Storefront also queries active
 *                                       + parent_id filters.
 *
 *   audit_logs(created_at) — StatsOverview counts WHERE created_at >= NOW - 1 day.
 *                            Also useful for admin audit view.
 *
 * ALL guarded by hasIndex checks so re-running is safe. All CREATE INDEX
 * without CONCURRENTLY (Laravel migrations use DDL transactions on some
 * drivers; the tables here are small enough that a brief lock is
 * acceptable during a maintenance window).
 */
return new class extends Migration {

    public function up(): void
    {
        // ─── users.status ────────────────────────────────────────────
        if (Schema::hasTable('users') && ! $this->hasIndex('users', 'users_status_idx')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('status', 'users_status_idx');
            });
        }

        // ─── vendors.status ─────────────────────────────────────────
        if (Schema::hasTable('vendors') && ! $this->hasIndex('vendors', 'vendors_status_idx')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->index('status', 'vendors_status_idx');
            });
        }

        // ─── products.status ───────────────────────────────────────
        // The (status, published_at) compound may already exist from earlier
        // phases; this migration adds a plain status index only if neither
        // exists.
        if (Schema::hasTable('products')
            && ! $this->hasIndex('products', 'products_status_idx')
            && ! $this->hasIndex('products', 'products_status_published_at_idx')
        ) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('status', 'products_status_idx');
            });
        }

        // ─── orders(status, payment_status) compound ────────────────
        if (Schema::hasTable('orders') && ! $this->hasIndex('orders', 'orders_status_payment_status_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'payment_status'], 'orders_status_payment_status_idx');
            });
        }

        // ─── categories(is_active) and (parent_id) ─────────────────
        if (Schema::hasTable('categories')) {
            if (! $this->hasIndex('categories', 'categories_is_active_idx')) {
                Schema::table('categories', function (Blueprint $table) {
                    $table->index('is_active', 'categories_is_active_idx');
                });
            }
            if (! $this->hasIndex('categories', 'categories_parent_id_idx')) {
                Schema::table('categories', function (Blueprint $table) {
                    $table->index('parent_id', 'categories_parent_id_idx');
                });
            }
        }

        // ─── audit_logs(created_at) ────────────────────────────────
        if (Schema::hasTable('audit_logs') && ! $this->hasIndex('audit_logs', 'audit_logs_created_at_idx')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('created_at', 'audit_logs_created_at_idx');
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'users'          => ['users_status_idx'],
            'vendors'        => ['vendors_status_idx'],
            'products'       => ['products_status_idx'],
            'orders'         => ['orders_status_payment_status_idx'],
            'categories'     => ['categories_is_active_idx', 'categories_parent_id_idx'],
            'audit_logs'     => ['audit_logs_created_at_idx'],
        ];
        foreach ($indexes as $tbl => $idxs) {
            if (! Schema::hasTable($tbl)) continue;
            Schema::table($tbl, function (Blueprint $table) use ($tbl, $idxs) {
                foreach ($idxs as $idx) {
                    if ($this->hasIndex($tbl, $idx)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }
    }

    /**
     * Cross-driver hasIndex check. `Schema::hasIndex` was added in Laravel 11
     * but is unreliable across drivers; do this manually.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $driver = DB::connection()->getDriverName();
            return match ($driver) {
                'mysql', 'mariadb' => count(DB::select(
                    "SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]
                )) > 0,
                'pgsql' => count(DB::select(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $indexName]
                )) > 0,
                'sqlite' => count(DB::select(
                    "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ?",
                    [$indexName]
                )) > 0,
                default => false,
            };
        } catch (\Throwable) {
            return false;  // safe: pretend absent → attempt create (will noop if exists)
        }
    }
};
