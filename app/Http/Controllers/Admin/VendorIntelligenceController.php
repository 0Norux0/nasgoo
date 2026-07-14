<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorIntelligenceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase 11B.4 §19 §41 — admin overview of vendor intelligence.
 *
 * Only super_admin. Shows aggregate per-vendor summaries. NEVER exposes
 * customer-level personalization data or private support-ticket text.
 * §24 §19 "Admin must not see private customer-level personalization details."
 *
 * Paginated + filterable so admin doesn't try to load 10k vendors at once
 * (dev §11 "no all-vendor calculation during one dashboard request").
 */
class VendorIntelligenceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorizeSuperAdmin($request);

        $filter = $request->query('filter'); // low_stock | incomplete_stores | missing_arabic | many_pending

        $query = VendorIntelligenceSummary::query()
            ->join('vendors', 'vendors.id', '=', 'vendor_intelligence_summaries.vendor_id')
            ->where('vendors.status', 'approved')
            ->select([
                'vendor_intelligence_summaries.*',
                'vendors.business_name',
                'vendors.business_email',
            ]);

        $query = match ($filter) {
            'low_stock' => $query
                ->where('vendor_intelligence_summaries.low_stock_count', '>', 0)
                ->orderByDesc('vendor_intelligence_summaries.low_stock_count'),
            'incomplete_stores' => $query
                ->where('vendor_intelligence_summaries.store_completion_score', '<', 80)
                ->orderBy('vendor_intelligence_summaries.store_completion_score'),
            'missing_arabic' => $query
                ->where('vendor_intelligence_summaries.missing_arabic_count', '>', 0)
                ->orderByDesc('vendor_intelligence_summaries.missing_arabic_count'),
            'many_pending' => $query
                ->where('vendor_intelligence_summaries.active_alerts_count', '>', 0)
                ->orderByDesc('vendor_intelligence_summaries.active_alerts_count'),
            default => $query->orderByDesc('vendor_intelligence_summaries.active_alerts_count'),
        };

        $summaries = $query->paginate(25)->withQueryString();

        // Marketplace-wide rollup (one query)
        $rollup = DB::table('vendor_intelligence_summaries')
            ->selectRaw('
                COUNT(*) AS total_vendors,
                SUM(active_alerts_count) AS total_alerts,
                AVG(store_completion_score) AS avg_completion,
                AVG(avg_product_quality) AS avg_quality
            ')
            ->first();

        return Inertia::render('Admin/VendorIntelligence/Overview', [
            'summaries' => $summaries,
            'filter'    => $filter,
            'rollup' => [
                'total_vendors'   => (int) ($rollup->total_vendors ?? 0),
                'total_alerts'    => (int) ($rollup->total_alerts ?? 0),
                'avg_completion'  => (int) round((float) ($rollup->avg_completion ?? 0)),
                'avg_quality'     => (int) round((float) ($rollup->avg_quality ?? 0)),
            ],
        ]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('super_admin'), 403);
    }
}
