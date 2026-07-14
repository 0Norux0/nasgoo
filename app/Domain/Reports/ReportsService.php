<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 — central reporting service.
 *
 * Every aggregate is queried directly from the source-of-truth tables
 * (orders, order_items, coupon_usages, vendor_payout_requests, vendors,
 * products, services, bookings, support_tickets, product_reviews) so
 * there is no risk of derived staleness. The financial KPIs are
 * computed from the same columns the CheckoutService writes:
 *
 *   subtotal     = SUM(orders.subtotal_minor)
 *   coupon disc. = SUM(orders.coupon_discount_minor)
 *   promo disc.  = SUM(orders.discount_minor - orders.coupon_discount_minor)
 *   shipping     = SUM(orders.shipping_minor)
 *   tax          = SUM(orders.tax_minor)
 *   gross total  = SUM(orders.total_minor)
 *   commission   = SUM(order_items.commission_amount_minor)
 *   vendor earn. = SUM(order_items.vendor_earning_minor)
 *
 * For multi-vendor orders, the vendor-scoped variant sums only over
 * order_items where vendor_id matches the requesting vendor. The
 * Phase 9 v9.3 allocation invariant
 *   sum(vendor_earning + commission) == subtotal − coupon_discount
 * is asserted in tests and re-verified by the CI invariant check.
 *
 * Date scoping is by orders.created_at by default; callers can pass a
 * different timestamp column via the constructor if needed.
 */
final class ReportsService
{
    /**
     * Resolve a preset to a [from, to] window using start/end of day.
     */
    public function resolveDateRange(string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = CarbonImmutable::now();
        return match ($preset) {
            'today'         => [$now->startOfDay(), $now->endOfDay()],
            'last_7_days'   => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days'  => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'this_month'    => [$now->startOfMonth(), $now->endOfMonth()],
            'previous_month' => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ],
            'custom'        => [
                $from ? CarbonImmutable::parse($from)->startOfDay() : $now->subDays(29)->startOfDay(),
                $to   ? CarbonImmutable::parse($to)->endOfDay()     : $now->endOfDay(),
            ],
            default         => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    /**
     * Admin-wide financial summary for a date range.
     * All amounts in minor units; callers format for display.
     */
    public function adminFinancialSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $orderStats = DB::table('orders')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])   // cancelled orders don't count toward revenue
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(subtotal_minor), 0) as subtotal_sum,
                COALESCE(SUM(shipping_minor), 0) as shipping_sum,
                COALESCE(SUM(tax_minor), 0) as tax_sum,
                COALESCE(SUM(discount_minor), 0) as discount_sum,
                COALESCE(SUM(coupon_discount_minor), 0) as coupon_sum,
                COALESCE(SUM(total_minor), 0) as total_sum
            ')
            ->first();

        // commission + vendor_earning are on order_items; join through to
        // restrict by the same date window.
        $itemStats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->selectRaw('
                COALESCE(SUM(order_items.commission_amount_minor), 0) as commission_sum,
                COALESCE(SUM(order_items.vendor_earning_minor), 0) as earnings_sum,
                COALESCE(SUM(order_items.coupon_allocation_minor), 0) as allocation_sum
            ')
            ->first();

        // Promo discount = total discount on orders MINUS the coupon portion
        // (since orders.discount_minor is the combined total). Coupon-allocation
        // sums to coupon_discount per the v9.3 reconciliation invariant.
        $couponSum   = (int) ($orderStats->coupon_sum ?? 0);
        $discountSum = (int) ($orderStats->discount_sum ?? 0);
        $promotionDiscountSum = max(0, $discountSum - $couponSum);

        $aov = ($orderStats->order_count ?? 0) > 0
            ? (int) round(((int) $orderStats->total_sum) / (int) $orderStats->order_count)
            : 0;

        return [
            'order_count'             => (int) ($orderStats->order_count ?? 0),
            'subtotal_minor'          => (int) ($orderStats->subtotal_sum ?? 0),
            'shipping_minor'          => (int) ($orderStats->shipping_sum ?? 0),
            'tax_minor'               => (int) ($orderStats->tax_sum ?? 0),
            'coupon_discount_minor'   => $couponSum,
            'promotion_discount_minor' => $promotionDiscountSum,
            'gross_total_minor'       => (int) ($orderStats->total_sum ?? 0),
            'commission_minor'        => (int) ($itemStats->commission_sum ?? 0),
            'vendor_earnings_minor'   => (int) ($itemStats->earnings_sum ?? 0),
            'allocation_minor'        => (int) ($itemStats->allocation_sum ?? 0),
            'aov_minor'               => $aov,
            // Phase 9 v9.3 financial reconciliation invariant:
            //   sum(commission + earnings) == subtotal − coupon_discount
            //   sum(allocation)            == coupon_discount
            // Surface so the dashboard can flag any non-zero delta.
            'reconciliation_delta_minor' => (int) ($itemStats->commission_sum ?? 0)
                + (int) ($itemStats->earnings_sum ?? 0)
                - ((int) ($orderStats->subtotal_sum ?? 0) - $couponSum),
            'allocation_delta_minor' => (int) ($itemStats->allocation_sum ?? 0) - $couponSum,
        ];
    }

    /**
     * Order-status breakdown for the same date window.
     */
    public function orderStatusBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('orders')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get();

        $byStatus = collect($rows)->mapWithKeys(fn ($r) => [$r->status => (int) $r->cnt])->all();
        $total = array_sum($byStatus);

        return [
            'pending_payment' => $byStatus['pending_payment'] ?? 0,
            'paid'            => $byStatus['paid']            ?? 0,
            'confirmed'       => $byStatus['confirmed']       ?? 0,
            'shipped'         => $byStatus['shipped']         ?? 0,
            'completed'       => $byStatus['completed']       ?? 0,
            'cancelled'       => $byStatus['cancelled']       ?? 0,
            'refunded'        => $byStatus['refunded']        ?? 0,
            'total'           => $total,
        ];
    }

    /**
     * Payout summary (all vendors).
     *
     * v10.11 §5 — column is `requested_amount_minor`, not `amount_minor`.
     * The vendor_payout_requests schema (migration
     * 2026_01_05_000003_create_vendor_payout_requests_table.php) has a single
     * amount column: `unsignedInteger('requested_amount_minor')`. Status
     * differentiates pending/approved/paid/rejected; the amount source is
     * always the requested amount. Pre-v10.11 queried `amount_minor` which
     * doesn't exist and produced:
     *   SQLSTATE[42S22]: Column not found: 1054
     *   Unknown column 'amount_minor' in 'field list'
     */
    public function adminPayoutSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('vendor_payout_requests')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COALESCE(SUM(requested_amount_minor), 0) as amount_sum, COUNT(*) as cnt')
            ->groupBy('status')
            ->get();

        $by = collect($rows)->mapWithKeys(fn ($r) => [$r->status => ['amount' => (int) $r->amount_sum, 'count' => (int) $r->cnt]])->all();

        return [
            'pending_amount_minor'   => $by['pending']['amount']   ?? 0,
            'pending_count'          => $by['pending']['count']    ?? 0,
            'approved_amount_minor'  => $by['approved']['amount']  ?? 0,
            'approved_count'         => $by['approved']['count']   ?? 0,
            'paid_amount_minor'      => $by['paid']['amount']      ?? 0,
            'paid_count'             => $by['paid']['count']       ?? 0,
            'rejected_amount_minor'  => $by['rejected']['amount']  ?? 0,
            'rejected_count'         => $by['rejected']['count']   ?? 0,
        ];
    }

    /**
     * Marketplace-wide counts (independent of date range — represent
     * "as of now" state, not movements). Includes vendor/product/service/
     * booking/ticket/review snapshots.
     */
    /**
     * Marketplace-wide counts (independent of date range — represent
     * "as of now" state, not movements). Includes vendor/product/service/
     * booking/ticket/review snapshots.
     *
     * Phase 10 v10.12 — canonical count definitions, documented here so
     * future readers don't have to guess:
     *
     * customers_total : users assigned the Spatie 'customer' role.
     *                   Pre-v10.12 queried `users.role` which doesn't
     *                   exist (this project uses Spatie Permission, not
     *                   a denormalized role column). The failing query
     *                   was `SELECT COUNT(*) FROM users WHERE role = ?`
     *                   producing `SQLSTATE[42S22] Unknown column 'role'`.
     *
     * vendors_*       : COUNT against the `vendors` table by `status`
     *                   column (real column on a real table). A vendor
     *                   user has a row in `vendors`; the row's status is
     *                   the canonical authority on whether they're an
     *                   approved/pending/rejected applicant. This is
     *                   distinct from holding the Spatie `vendor` role
     *                   — a vendor with status='rejected' may still hold
     *                   the role, but they're not an active vendor.
     *
     * products_*, services_*, bookings_*, support_tickets_*, reviews_* :
     *                   all query real columns on real tables. Verified
     *                   in v10.12 audit. No further role-keyed queries
     *                   exist anywhere in ReportsService.
     */
    public function marketplaceCounts(): array
    {
        return [
            // Phase 10 v10.12 — Spatie role scope replaces broken DB::where('role',...)
            'customers_total'         => (int) User::role('customer')->count(),
            'vendors_approved'        => (int) DB::table('vendors')->where('status', 'approved')->count(),
            'vendors_pending'         => (int) DB::table('vendors')->where('status', 'pending')->count(),
            'vendors_rejected'        => (int) DB::table('vendors')->where('status', 'rejected')->count(),
            'products_total'          => (int) DB::table('products')->where('type', '!=', 'service')->count(),
            'products_published'      => (int) DB::table('products')
                ->where('type', '!=', 'service')
                ->where('status', 'published')->count(),
            'services_total'          => (int) DB::table('products')->where('type', 'service')->count(),
            'services_published'      => (int) DB::table('products')
                ->where('type', 'service')
                ->where('status', 'published')->count(),
            'bookings_total'          => (int) DB::table('service_bookings')->count(),
            'support_tickets_open'    => (int) DB::table('support_tickets')
                ->whereIn('status', ['open', 'pending'])->count(),
            'support_tickets_total'   => (int) DB::table('support_tickets')->count(),
            'reviews_approved'        => (int) DB::table('product_reviews')->where('status', 'approved')->count(),
            'reviews_pending'         => (int) DB::table('product_reviews')->where('status', 'pending')->count(),
            'reviews_avg_rating'      => round(
                (float) (DB::table('product_reviews')->where('status', 'approved')->avg('rating') ?? 0),
                2
            ),
        ];
    }

    /**
     * Top vendors by gross sales in the window.
     */
    public function topVendorsByGross(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('vendors', 'vendors.id', '=', 'order_items.vendor_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->selectRaw('
                vendors.id, vendors.business_name,
                COUNT(DISTINCT orders.id) as order_count,
                COALESCE(SUM(order_items.line_total_minor), 0) as gross_minor,
                COALESCE(SUM(order_items.coupon_allocation_minor), 0) as allocation_minor,
                COALESCE(SUM(order_items.commission_amount_minor), 0) as commission_minor,
                COALESCE(SUM(order_items.vendor_earning_minor), 0) as earnings_minor
            ')
            ->groupBy('vendors.id', 'vendors.business_name')
            ->orderByDesc('gross_minor')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Top products by units sold in the window.
     */
    public function topProductsByUnits(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->whereNotNull('order_items.product_id')
            ->selectRaw('
                order_items.product_id,
                MAX(order_items.product_name) as product_name,
                SUM(order_items.quantity) as units_sold,
                SUM(order_items.line_total_minor) as gross_minor
            ')
            ->groupBy('order_items.product_id')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Vendor-scoped financial summary. Every aggregate is restricted to
     * order_items.vendor_id == $vendorId so a vendor never sees another
     * vendor's data.
     */
    public function vendorFinancialSummary(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $stats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->selectRaw('
                COUNT(DISTINCT orders.id) as order_count,
                COALESCE(SUM(order_items.line_total_minor), 0) as gross_minor,
                COALESCE(SUM(order_items.coupon_allocation_minor), 0) as allocation_minor,
                COALESCE(SUM(order_items.commission_amount_minor), 0) as commission_minor,
                COALESCE(SUM(order_items.vendor_earning_minor), 0) as earnings_minor,
                SUM(order_items.quantity) as units_sold
            ')
            ->first();

        // v10.11 §5 — column is requested_amount_minor (see adminPayoutSummary above)
        $payoutStats = DB::table('vendor_payout_requests')
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COALESCE(SUM(requested_amount_minor), 0) as amount_sum')
            ->groupBy('status')
            ->get();

        $payoutBy = collect($payoutStats)->mapWithKeys(fn ($r) => [$r->status => (int) $r->amount_sum])->all();

        $reviewsAvg = (float) (DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.vendor_id', $vendorId)
            ->where('product_reviews.status', 'approved')
            ->avg('product_reviews.rating') ?? 0);

        $reviewsCount = (int) DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.vendor_id', $vendorId)
            ->where('product_reviews.status', 'approved')
            ->count();

        return [
            'order_count'           => (int) ($stats->order_count ?? 0),
            'units_sold'            => (int) ($stats->units_sold ?? 0),
            'gross_minor'           => (int) ($stats->gross_minor ?? 0),
            'allocation_minor'      => (int) ($stats->allocation_minor ?? 0),
            'net_minor'             => max(0, (int) ($stats->gross_minor ?? 0) - (int) ($stats->allocation_minor ?? 0)),
            'commission_minor'      => (int) ($stats->commission_minor ?? 0),
            'earnings_minor'        => (int) ($stats->earnings_minor ?? 0),
            'payout_pending_minor'  => $payoutBy['pending']  ?? 0,
            'payout_approved_minor' => $payoutBy['approved'] ?? 0,
            'payout_paid_minor'     => $payoutBy['paid']     ?? 0,
            'reviews_count'         => $reviewsCount,
            'reviews_avg_rating'    => round($reviewsAvg, 2),
        ];
    }

    /**
     * Per-product performance for the requesting vendor.
     */
    public function vendorProductPerformance(int $vendorId, CarbonInterface $from, CarbonInterface $to, int $limit = 25): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->whereNotNull('order_items.product_id')
            ->selectRaw('
                order_items.product_id,
                MAX(order_items.product_name) as product_name,
                SUM(order_items.quantity) as units_sold,
                COUNT(DISTINCT orders.id) as order_count,
                SUM(order_items.line_total_minor) as gross_minor,
                SUM(order_items.coupon_allocation_minor) as allocation_minor,
                SUM(order_items.commission_amount_minor) as commission_minor,
                SUM(order_items.vendor_earning_minor) as earnings_minor
            ')
            ->groupBy('order_items.product_id')
            ->orderByDesc('gross_minor')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Daily revenue series for a sparkline / line chart.
     * Returns array of [date_ymd, total_minor, order_count].
     */
    public function dailyRevenueSeries(CarbonInterface $from, CarbonInterface $to, ?int $vendorId = null): array
    {
        $q = DB::table('orders')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled']);

        if ($vendorId !== null) {
            // Restrict to orders that have at least one line for this vendor
            $q->whereExists(function (QueryBuilder $sub) use ($vendorId) {
                $sub->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.vendor_id', $vendorId);
            });
        }

        // MySQL + PostgreSQL portable: DATE(created_at)
        return $q->selectRaw('DATE(created_at) as ymd, COALESCE(SUM(total_minor),0) as total_minor, COUNT(*) as order_count')
            ->groupBy('ymd')
            ->orderBy('ymd')
            ->get()
            ->map(fn ($r) => [
                'date'        => (string) $r->ymd,
                'total_minor' => (int) $r->total_minor,
                'orders'      => (int) $r->order_count,
            ])
            ->all();
    }
}
