<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\ReportsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 10 — Admin reporting dashboard.
 *
 * Authorization: `reports.view` permission, granted to super_admin and
 * admin_staff by the Phase 10 role/permission migration. Customers and
 * vendors get 403.
 *
 * Date filter contract:
 *   ?preset=today|last_7_days|last_30_days|this_month|previous_month|custom
 *   ?from=YYYY-MM-DD&to=YYYY-MM-DD  (only when preset=custom)
 *
 * Export contract:
 *   GET /admin/reports/export.csv?<same filter params>
 *   Returns a streamed CSV download of the order-level financial summary.
 */
class ReportsController extends Controller
{
    public function __construct(private readonly ReportsService $reports) {}

    public function index(Request $request): Response
    {
        $this->guardAdminReportsAccess($request);

        [$from, $to] = $this->reports->resolveDateRange(
            (string) $request->query('preset', 'last_30_days'),
            $request->query('from'),
            $request->query('to'),
        );

        $financial = $this->reports->adminFinancialSummary($from, $to);
        $statuses  = $this->reports->orderStatusBreakdown($from, $to);
        $payouts   = $this->reports->adminPayoutSummary($from, $to);
        $counts    = $this->reports->marketplaceCounts();
        $vendors   = $this->reports->topVendorsByGross($from, $to, 10);
        $products  = $this->reports->topProductsByUnits($from, $to, 10);
        $series    = $this->reports->dailyRevenueSeries($from, $to);

        return Inertia::render('Admin/Reports/Index', [
            'filter' => [
                'preset' => (string) $request->query('preset', 'last_30_days'),
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
            ],
            'financial' => $this->presentFinancial($financial),
            'statuses'  => $statuses,
            'payouts'   => $this->presentPayouts($payouts),
            'counts'    => $counts,
            'vendors'   => array_map(fn ($v) => $this->presentVendor($v), $vendors),
            'products'  => array_map(fn ($p) => $this->presentProduct($p), $products),
            'series'    => array_map(fn ($d) => [
                'date'   => $d['date'],
                'total'  => number_format($d['total_minor'] / 100, 2),
                'total_minor' => $d['total_minor'],
                'orders' => $d['orders'],
            ], $series),
            'currency'  => config('marketplace.default_currency', 'KWD'),
        ]);
    }

    /**
     * Streamed CSV download of the order-level financial summary
     * for the date window. Respects the same filter parameters.
     *
     * Streamed (not buffered) so large windows don't OOM the PHP process.
     */
    public function exportOrdersCsv(Request $request): StreamedResponse
    {
        $this->guardAdminReportsAccess($request);

        [$from, $to] = $this->reports->resolveDateRange(
            (string) $request->query('preset', 'last_30_days'),
            $request->query('from'),
            $request->query('to'),
        );

        $filename = sprintf('marketplace-orders-%s-to-%s.csv', $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'order_number', 'placed_at', 'status', 'payment_status', 'fulfillment_status',
                'currency', 'subtotal', 'shipping', 'tax', 'discount',
                'coupon_code', 'coupon_discount', 'total',
                'customer_email',
            ]);

            \App\Models\Order::query()
                ->with('user:id,email')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('id')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $o) {
                        fputcsv($out, [
                            $o->number,
                            $o->created_at?->toDateTimeString(),
                            $o->status,
                            $o->payment_status,
                            $o->fulfillment_status,
                            $o->currency,
                            number_format($o->subtotal_minor / 100, 2),
                            number_format($o->shipping_minor / 100, 2),
                            number_format($o->tax_minor / 100, 2),
                            number_format($o->discount_minor / 100, 2),
                            $o->coupon_code ?? '',
                            number_format(((int) $o->coupon_discount_minor) / 100, 2),
                            number_format($o->total_minor / 100, 2),
                            $o->user?->email ?? '',
                        ]);
                    }
                });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    // ─────────── presenters ───────────

    private function presentFinancial(array $f): array
    {
        return [
            'order_count'              => $f['order_count'],
            'subtotal'                 => number_format($f['subtotal_minor'] / 100, 2),
            'subtotal_minor'           => $f['subtotal_minor'],
            'shipping'                 => number_format($f['shipping_minor'] / 100, 2),
            'tax'                      => number_format($f['tax_minor'] / 100, 2),
            'coupon_discount'          => number_format($f['coupon_discount_minor'] / 100, 2),
            'coupon_discount_minor'    => $f['coupon_discount_minor'],
            'promotion_discount'       => number_format($f['promotion_discount_minor'] / 100, 2),
            'gross_total'              => number_format($f['gross_total_minor'] / 100, 2),
            'gross_total_minor'        => $f['gross_total_minor'],
            'commission'               => number_format($f['commission_minor'] / 100, 2),
            'commission_minor'         => $f['commission_minor'],
            'vendor_earnings'          => number_format($f['vendor_earnings_minor'] / 100, 2),
            'vendor_earnings_minor'    => $f['vendor_earnings_minor'],
            'allocation'               => number_format($f['allocation_minor'] / 100, 2),
            'aov'                      => number_format($f['aov_minor'] / 100, 2),
            'aov_minor'                => $f['aov_minor'],
            'reconciliation_delta_minor' => $f['reconciliation_delta_minor'],
            'allocation_delta_minor'   => $f['allocation_delta_minor'],
        ];
    }

    private function presentPayouts(array $p): array
    {
        return [
            'pending'  => ['count' => $p['pending_count'],  'amount' => number_format($p['pending_amount_minor'] / 100, 2),  'amount_minor' => $p['pending_amount_minor']],
            'approved' => ['count' => $p['approved_count'], 'amount' => number_format($p['approved_amount_minor'] / 100, 2), 'amount_minor' => $p['approved_amount_minor']],
            'paid'     => ['count' => $p['paid_count'],     'amount' => number_format($p['paid_amount_minor'] / 100, 2),     'amount_minor' => $p['paid_amount_minor']],
            'rejected' => ['count' => $p['rejected_count'], 'amount' => number_format($p['rejected_amount_minor'] / 100, 2), 'amount_minor' => $p['rejected_amount_minor']],
        ];
    }

    private function presentVendor(array $v): array
    {
        return [
            'id'             => $v['id'],
            'business_name'  => $v['business_name'],
            'order_count'    => (int) $v['order_count'],
            'gross'          => number_format(((int) $v['gross_minor']) / 100, 2),
            'gross_minor'    => (int) $v['gross_minor'],
            'allocation'     => number_format(((int) $v['allocation_minor']) / 100, 2),
            'commission'     => number_format(((int) $v['commission_minor']) / 100, 2),
            'earnings'       => number_format(((int) $v['earnings_minor']) / 100, 2),
            'earnings_minor' => (int) $v['earnings_minor'],
        ];
    }

    private function presentProduct(array $p): array
    {
        return [
            'product_id'     => $p['product_id'],
            'product_name'   => $p['product_name'],
            'units_sold'     => (int) $p['units_sold'],
            'gross'          => number_format(((int) $p['gross_minor']) / 100, 2),
            'gross_minor'    => (int) $p['gross_minor'],
        ];
    }
    /**
     * Phase 10 v10.10 — direct authorization check.
     *
     * Pre-v10.10 used the policy-style authorize() call against the User model class,
     * which runs through 4 layers of indirection (AuthorizesRequests trait,
     * policy auto-discovery for UserPolicy::viewReports, Gate::before
     * super_admin shortcut, defined Gate). Any of those four layers can
     * drift between releases or installations. v10.10 collapses the check
     * to a direct method call on the user. Zero indirection.
     *
     * The canonical rule is User::canManageAdminReports(). Anything that
     * returns false there → 403. Anything else → page renders. Identical
     * semantics to v10.9 but mechanically simpler — there is no second
     * layer to drift. The try/catch around the role lookup guards against
     * the case where Spatie's role table is unreachable (e.g. permission
     * cache corruption in production). A 403 is the safe outcome — never
     * a 500.
     */
    private function guardAdminReportsAccess(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You must be signed in to access admin reports.');
        }

        try {
            $authorized = $user->canManageAdminReports();
        } catch (\Throwable $e) {
            logger()?->warning('Phase 10 v10.10 — admin reports access guard threw', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);
            abort(403, 'Unable to verify admin reports access. Run `php artisan reports:diagnose-access` to inspect.');
        }

        abort_unless(
            $authorized,
            403,
            'Admin reports access requires the super_admin or admin_staff role. '
            . 'Run `php artisan reports:diagnose-access ' . ($user->email ?? '') . '` for diagnostics, '
            . 'or `php artisan reports:repair-access ' . ($user->email ?? '') . '` to repair.'
        );
    }
}
