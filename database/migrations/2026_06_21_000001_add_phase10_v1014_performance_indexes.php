<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 v10.14 — additive performance indexes (gaps left after v10.1).
 *
 * v10.1 covered reports-query patterns (orders.created_at+status,
 * order_items.vendor_id+order_id, products.status+type, etc.). v10.14
 * adds indexes for query patterns that the controller list pages run
 * on every render:
 *
 *   CustomerOrderController::index
 *     SELECT ... FROM orders WHERE user_id = ? ORDER BY created_at DESC
 *
 *   VendorOrderController::index
 *     SELECT DISTINCT orders.* FROM orders
 *      JOIN order_items ON ... WHERE order_items.vendor_id = ?
 *      ORDER BY orders.created_at DESC
 *     → order_items.vendor_id already indexed (v10.1)
 *     → adding orders.(user_id, created_at) for the customer side
 *
 *   SupportTicketController::index (customer)
 *     SELECT ... FROM support_tickets WHERE user_id = ?
 *      AND status IN (...) ORDER BY created_at DESC
 *
 *   VendorSupportTicketController::index (vendor)
 *     SELECT ... FROM support_tickets WHERE vendor_id = ?
 *      AND status IN (...) ORDER BY created_at DESC
 *
 *   Filament SupportTicketResource list (admin)
 *     SELECT ... FROM support_tickets WHERE status = ?
 *      ORDER BY created_at DESC
 *
 *   Filament VendorResource list (admin)
 *     SELECT ... FROM vendors WHERE status = ? ORDER BY created_at
 *
 * All composite indexes are designed so MySQL can use the index for
 * BOTH the WHERE filter AND the ORDER BY (eliminating filesort). Index
 * names stay well under the 64-char MySQL identifier limit (v8.2
 * defense). Idempotent: each addition is wrapped in a hasIndex() check
 * so this migration is safe to re-run.
 *
 * NO TABLE COLUMN CHANGES. Indexes only. Zero risk to data.
 */
return new class extends Migration {
    public function up(): void
    {
        // Customer orders: "my orders sorted newest"
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasIndex('orders', 'orders_user_created_idx')) {
                $table->index(['user_id', 'created_at'], 'orders_user_created_idx');
            }
            // Admin status filter + sort
            if (! $this->hasIndex('orders', 'orders_status_created_idx')) {
                $table->index(['status', 'created_at'], 'orders_status_created_idx');
            }
        });

        // Support tickets: customer + vendor + admin list filters
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (! $this->hasIndex('support_tickets', 'st_user_status_created_idx')) {
                    $table->index(['user_id', 'status', 'created_at'], 'st_user_status_created_idx');
                }
                if (! $this->hasIndex('support_tickets', 'st_vendor_status_created_idx')) {
                    $table->index(['vendor_id', 'status', 'created_at'], 'st_vendor_status_created_idx');
                }
                if (! $this->hasIndex('support_tickets', 'st_status_created_idx')) {
                    $table->index(['status', 'created_at'], 'st_status_created_idx');
                }
            });
        }

        // support_ticket_messages — Filament Infolist iterates
        // messages.user; the eager-load is already a single IN(user_ids)
        // query. The list ordering is by created_at within a given
        // ticket. Add the index to make per-ticket ordered fetches a
        // direct lookup instead of a per-row scan.
        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table) {
                if (! $this->hasIndex('support_ticket_messages', 'stm_ticket_created_idx')) {
                    $table->index(['support_ticket_id', 'created_at'], 'stm_ticket_created_idx');
                }
            });
        }

        // Vendors: admin Filament list filters by status
        Schema::table('vendors', function (Blueprint $table) {
            if (! $this->hasIndex('vendors', 'vendors_status_created_idx')) {
                $table->index(['status', 'created_at'], 'vendors_status_created_idx');
            }
        });

        // Vendor payout requests: per-vendor status + date filtering
        // (admin payout console + vendor self-list)
        Schema::table('vendor_payout_requests', function (Blueprint $table) {
            if (! $this->hasIndex('vendor_payout_requests', 'vpr_vendor_status_created_idx')) {
                $table->index(['vendor_id', 'status', 'created_at'], 'vpr_vendor_status_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders', 'orders_user_created_idx')) {
                $table->dropIndex('orders_user_created_idx');
            }
            if ($this->hasIndex('orders', 'orders_status_created_idx')) {
                $table->dropIndex('orders_status_created_idx');
            }
        });

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                foreach (['st_user_status_created_idx', 'st_vendor_status_created_idx', 'st_status_created_idx'] as $name) {
                    if ($this->hasIndex('support_tickets', $name)) {
                        $table->dropIndex($name);
                    }
                }
            });
        }

        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table) {
                if ($this->hasIndex('support_ticket_messages', 'stm_ticket_created_idx')) {
                    $table->dropIndex('stm_ticket_created_idx');
                }
            });
        }

        Schema::table('vendors', function (Blueprint $table) {
            if ($this->hasIndex('vendors', 'vendors_status_created_idx')) {
                $table->dropIndex('vendors_status_created_idx');
            }
        });

        Schema::table('vendor_payout_requests', function (Blueprint $table) {
            if ($this->hasIndex('vendor_payout_requests', 'vpr_vendor_status_created_idx')) {
                $table->dropIndex('vpr_vendor_status_created_idx');
            }
        });
    }

    /**
     * Portable index-existence check. doctrine/dbal isn't required in
     * Laravel 11; we use the SchemaBuilder's getIndexes() introspection.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $idx = Schema::getConnection()
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
