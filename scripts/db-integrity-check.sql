-- ═══════════════════════════════════════════════════════════════════════════
-- Phase 12 — Production Database Integrity Diagnostics
-- ═══════════════════════════════════════════════════════════════════════════
--
-- All queries below are READ-ONLY. Safe on a live production database.
-- Recommended usage:
--
--   mysql -u DB_USERNAME -p DB_DATABASE < scripts/db-integrity-check.sql \
--         > integrity-report-$(date +%F).txt
--
-- Each SELECT returns rows that indicate a problem. An empty result on
-- every query means the database is clean.
--
-- If a query returns rows, DO NOT delete them without a backup. Some
-- "orphans" are intentional (e.g. deleted user with retained order
-- history for financial audit).
-- ═══════════════════════════════════════════════════════════════════════════

-- ─── 1. Orphaned product images (product row gone) ──────────────────
-- Product deletion should cascade; rows here indicate a manual DELETE
-- that bypassed the FK, or an FK that was never enforced.
SELECT 'orphaned_product_images' AS check_name, COUNT(*) AS count
FROM product_images pi
LEFT JOIN products p ON p.id = pi.product_id
WHERE p.id IS NULL;

-- ─── 2. Orphaned order items (order row gone) ───────────────────────
SELECT 'orphaned_order_items' AS check_name, COUNT(*) AS count
FROM order_items oi
LEFT JOIN orders o ON o.id = oi.order_id
WHERE o.id IS NULL;

-- ─── 3. Orphaned vendor products (vendor row gone) ──────────────────
SELECT 'orphaned_vendor_products' AS check_name, COUNT(*) AS count
FROM products p
LEFT JOIN vendors v ON v.id = p.vendor_id
WHERE v.id IS NULL;

-- ─── 4. Orphaned product translations ───────────────────────────────
-- FK cascadeOnDelete is defined in the migration, but historical rows
-- from before the FK was added could still be orphaned.
SELECT 'orphaned_product_translations' AS check_name, COUNT(*) AS count
FROM product_translations pt
LEFT JOIN products p ON p.id = pt.product_id
WHERE p.id IS NULL;

-- ─── 5. Orphaned recommendation events (source product deleted) ─────
SELECT 'orphaned_rec_events_source' AS check_name, COUNT(*) AS count
FROM recommendation_events re
LEFT JOIN products p ON p.id = re.product_id
WHERE p.id IS NULL;

-- ─── 6. Orphaned vendor intelligence alerts (vendor gone) ───────────
SELECT 'orphaned_vi_alerts' AS check_name, COUNT(*) AS count
FROM vendor_intelligence_alerts a
LEFT JOIN vendors v ON v.id = a.vendor_id
WHERE v.id IS NULL;

-- ─── 7. Duplicate active vendor intelligence alerts ─────────────────
-- With v11B.4.2's UNIQUE via_active_dedupe_uniq on active_dedupe_key
-- this should ALWAYS return 0. If it returns rows, the UNIQUE index is
-- missing or the migration wasn't applied.
SELECT 'duplicate_active_alerts' AS check_name, COUNT(*) AS count
FROM (
    SELECT active_dedupe_key, COUNT(*) c
    FROM vendor_intelligence_alerts
    WHERE status = 'active' AND active_dedupe_key IS NOT NULL
    GROUP BY active_dedupe_key
    HAVING c > 1
) x;

-- ─── 8. Orders with mismatched item totals ──────────────────────────
-- items subtotal + shipping + tax - discount should equal total_minor.
-- Small rounding differences are fine (Δ ≤ 1 fils = 0.001 KWD).
-- Column names verified against 2026_01_04_000002 orders migration:
--   subtotal_minor, shipping_minor, tax_minor, discount_minor, total_minor
SELECT 'orders_total_mismatch' AS check_name, COUNT(*) AS count
FROM orders o
WHERE ABS((
    COALESCE(o.subtotal_minor,0)
    + COALESCE(o.shipping_minor,0)
    + COALESCE(o.tax_minor,0)
    - COALESCE(o.discount_minor,0)
) - COALESCE(o.total_minor,0)) > 1;

-- ─── 9. Users with no role assigned ─────────────────────────────────
-- Every user must have exactly one primary role. Users here won't have
-- correct authorization and are usually the result of a failed signup
-- or an aborted seeder.
--
-- IMPORTANT: model_type is stored by Spatie as literal 'App\Models\User'
-- (one backslash between namespace segments). In MySQL string literals
-- with the default sql_mode, backslash IS an escape character, so we
-- must write '\\' in the SQL to get a single '\' in the comparison
-- value. Do NOT use four backslashes here — that would look for
-- 'App\\Models\\User' (two backslashes) which does not exist.
SELECT 'users_without_role' AS check_name, COUNT(*) AS count
FROM users u
LEFT JOIN model_has_roles mhr
       ON mhr.model_id = u.id
      AND mhr.model_type = 'App\\Models\\User'
WHERE mhr.role_id IS NULL;

-- ─── 10. Vendors without a corresponding user row ───────────────────
SELECT 'vendors_without_user' AS check_name, COUNT(*) AS count
FROM vendors v
LEFT JOIN users u ON u.id = v.user_id
WHERE u.id IS NULL;

-- ─── 11. Approved vendors with no active subscription ───────────────
-- Not necessarily wrong (grandfathered accounts), but flags accounts
-- that should be checked before commission calculations.
SELECT 'approved_vendors_no_subscription' AS check_name, COUNT(*) AS count
FROM vendors v
LEFT JOIN vendor_subscriptions vs
       ON vs.vendor_id = v.id AND vs.status = 'active'
WHERE v.status = 'approved' AND vs.id IS NULL;

-- ─── 12. Products with negative stock ───────────────────────────────
-- products.stock is `integer DEFAULT 0` (non-nullable), so `stock IS NULL`
-- can never be true. The meaningful integrity check on this column is
-- for NEGATIVE stock — should never happen given inventory decrement
-- guards, but detects data corruption if it does.
SELECT 'products_negative_stock' AS check_name, COUNT(*) AS count
FROM products
WHERE track_stock = 1 AND stock < 0;

-- ─── 13. Published products missing required category ──────────────
SELECT 'published_products_no_category' AS check_name, COUNT(*) AS count
FROM products
WHERE status = 'published' AND category_id IS NULL;

-- ─── 14. Cart items where the product is deleted ────────────────────
-- Should cascade, but old carts + soft-deleted products may leave stubs.
SELECT 'cart_items_dead_product' AS check_name, COUNT(*) AS count
FROM cart_items ci
LEFT JOIN products p ON p.id = ci.product_id
WHERE p.id IS NULL;

-- ─── 15. Coupon usages with no matching coupon ──────────────────────
SELECT 'coupon_usages_dead_coupon' AS check_name, COUNT(*) AS count
FROM coupon_usages cu
LEFT JOIN coupons c ON c.id = cu.coupon_id
WHERE c.id IS NULL;

-- ─── 16. Payments not linked to any order ───────────────────────────
SELECT 'payments_no_order' AS check_name, COUNT(*) AS count
FROM payments p
LEFT JOIN orders o ON o.id = p.order_id
WHERE o.id IS NULL;

-- ─── 17. Duplicate product SKUs per vendor ──────────────────────────
-- UNIQUE(vendor_id, sku) exists, this should always be 0. If not, the
-- UNIQUE constraint is missing or was dropped.
SELECT 'duplicate_sku_per_vendor' AS check_name, COUNT(*) AS count
FROM (
    SELECT vendor_id, sku, COUNT(*) c
    FROM products
    WHERE sku IS NOT NULL AND sku != ''
    GROUP BY vendor_id, sku
    HAVING c > 1
) x;

-- ─── 18. Search queries with is_blocked=1 but still high count ──────
-- Blocked queries shouldn't be incremented; if they are, the block
-- guard is missing on a code path.
SELECT 'blocked_queries_still_counting' AS check_name, COUNT(*) AS count
FROM search_queries
WHERE is_blocked = 1 AND last_searched_at > NOW() - INTERVAL 7 DAY;

-- ─── 19. Product reviews with rating out of range ───────────────────
SELECT 'reviews_out_of_range' AS check_name, COUNT(*) AS count
FROM product_reviews
WHERE rating < 1 OR rating > 5;

-- ─── 20. Service bookings for non-service products ──────────────────
SELECT 'bookings_wrong_product_type' AS check_name, COUNT(*) AS count
FROM service_bookings sb
JOIN products p ON p.id = sb.product_id
WHERE p.type != 'service';

-- ═══════════════════════════════════════════════════════════════════════════
-- SUMMARY: run the 20 queries above. Any non-zero count is a data
-- integrity issue that should be investigated (and usually resolved via
-- a targeted UPDATE/DELETE with prior backup) before production launch.
-- ═══════════════════════════════════════════════════════════════════════════
