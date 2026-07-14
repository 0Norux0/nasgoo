<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Reports\ReportsService;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 10 — Vendor reporting page.
 *
 * The vendor is resolved from request attributes (set by the vendor
 * route middleware) — NEVER from a request parameter. This guarantees
 * a vendor cannot pass `?vendor_id=N` and read another vendor's data.
 *
 * All ReportsService methods used here are vendor-scoped: each query
 * filters by order_items.vendor_id = $vendor->id.
 */
class VendorReportsController extends Controller
{
    public function __construct(private readonly ReportsService $reports) {}

    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        [$from, $to] = $this->reports->resolveDateRange(
            (string) $request->query('preset', 'last_30_days'),
            $request->query('from'),
            $request->query('to'),
        );

        $financial = $this->reports->vendorFinancialSummary($vendor->id, $from, $to);
        $products  = $this->reports->vendorProductPerformance($vendor->id, $from, $to, 25);
        $series    = $this->reports->dailyRevenueSeries($from, $to, $vendor->id);

        return Inertia::render('Vendor/Reports/Index', [
            'filter' => [
                'preset' => (string) $request->query('preset', 'last_30_days'),
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
            ],
            'financial' => [
                'order_count'           => $financial['order_count'],
                'units_sold'            => $financial['units_sold'],
                'gross'                 => number_format($financial['gross_minor'] / 100, 2),
                'gross_minor'           => $financial['gross_minor'],
                'allocation'            => number_format($financial['allocation_minor'] / 100, 2),
                'net'                   => number_format($financial['net_minor'] / 100, 2),
                'net_minor'             => $financial['net_minor'],
                'commission'            => number_format($financial['commission_minor'] / 100, 2),
                'commission_minor'      => $financial['commission_minor'],
                'earnings'              => number_format($financial['earnings_minor'] / 100, 2),
                'earnings_minor'        => $financial['earnings_minor'],
                'payout_pending'        => number_format($financial['payout_pending_minor'] / 100, 2),
                'payout_approved'       => number_format($financial['payout_approved_minor'] / 100, 2),
                'payout_paid'           => number_format($financial['payout_paid_minor'] / 100, 2),
                'reviews_count'         => $financial['reviews_count'],
                'reviews_avg_rating'    => $financial['reviews_avg_rating'],
            ],
            'products' => array_map(fn ($p) => [
                'product_id'     => $p['product_id'],
                'product_name'   => $p['product_name'],
                'units_sold'     => (int) $p['units_sold'],
                'order_count'    => (int) $p['order_count'],
                'gross'          => number_format(((int) $p['gross_minor']) / 100, 2),
                'allocation'     => number_format(((int) $p['allocation_minor']) / 100, 2),
                'commission'    => number_format(((int) $p['commission_minor']) / 100, 2),
                'earnings'       => number_format(((int) $p['earnings_minor']) / 100, 2),
                'earnings_minor' => (int) $p['earnings_minor'],
            ], $products),
            'series' => array_map(fn ($d) => [
                'date'        => $d['date'],
                'total'       => number_format($d['total_minor'] / 100, 2),
                'total_minor' => $d['total_minor'],
                'orders'      => $d['orders'],
            ], $series),
            'vendor' => [
                'id'            => $vendor->id,
                'business_name' => $vendor->business_name,
            ],
            'currency' => $vendor->currency ?? config('marketplace.default_currency', 'KWD'),
        ]);
    }

    /**
     * Vendor-scoped CSV export of THEIR order items in the window.
     * The vendor cannot see other vendors' lines.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        [$from, $to] = $this->reports->resolveDateRange(
            (string) $request->query('preset', 'last_30_days'),
            $request->query('from'),
            $request->query('to'),
        );

        $filename = sprintf(
            'vendor-%d-orders-%s-to-%s.csv',
            $vendor->id,
            $from->toDateString(),
            $to->toDateString(),
        );

        return response()->streamDownload(function () use ($vendor, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM for Excel
            fputcsv($out, [
                'order_number', 'placed_at', 'product_name', 'variant_name',
                'quantity', 'unit_price', 'line_total',
                'coupon_allocation', 'net_line_total',
                'commission_percent', 'commission', 'vendor_earning',
                'fulfillment', 'currency',
            ]);

            \App\Models\OrderItem::query()
                ->where('vendor_id', $vendor->id)
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereNotIn('orders.status', ['cancelled'])
                ->orderBy('orders.created_at')
                ->select(
                    'order_items.*',
                    'orders.number as order_number',
                    'orders.created_at as order_placed_at',
                    'orders.currency as order_currency',
                )
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $i) {
                        $netLine = max(0, $i->line_total_minor - (int) $i->coupon_allocation_minor);
                        fputcsv($out, [
                            $i->order_number,
                            $i->order_placed_at,
                            $i->product_name,
                            $i->variant_name ?? '',
                            $i->quantity,
                            number_format($i->unit_price_minor / 100, 2),
                            number_format($i->line_total_minor / 100, 2),
                            number_format(((int) $i->coupon_allocation_minor) / 100, 2),
                            number_format($netLine / 100, 2),
                            $i->commission_percent,
                            number_format($i->commission_amount_minor / 100, 2),
                            number_format($i->vendor_earning_minor / 100, 2),
                            $i->fulfillment_status,
                            $i->order_currency,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
