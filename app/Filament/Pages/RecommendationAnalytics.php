<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RecommendationEvent;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.2 §22 — basic admin analytics page for recommendation
 * performance. Aggregates events, never displays individual customers.
 *
 * Per dev §22 — "Do not begin the full plain-language reporting phase.
 * This view should display basic recommendation performance only."
 *
 * All metrics are aggregated across users; the user_id column on
 * recommendation_events is used ONLY for conversion attribution joins,
 * never displayed here per dev §21.
 */
class RecommendationAnalytics extends Page
{
    protected static ?string $navigationGroup = 'Recommendations';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?int    $navigationSort  = 20;

    protected static string $view = 'filament.pages.recommendation-analytics';

    public ?int $days = 30;

    /**
     * Aggregate per-type metrics for the dashboard. Single SQL pass:
     * GROUP BY recommendation_type + event_type → counts.
     *
     * @return array<int,array{type:string,impressions:int,clicks:int,add_to_cart:int,ctr:float,a2c_rate:float}>
     */
    public function getMetricsProperty(): array
    {
        $cutoff = now()->subDays($this->days);

        $rows = DB::table('recommendation_events')
            ->select('recommendation_type', 'event_type', DB::raw('COUNT(*) as c'))
            ->where('created_at', '>=', $cutoff)
            // v11B.2.1 §3 — count only NON-reversed purchases for net conversions.
            // Refunded/cancelled events keep their row (reversed_at != NULL) so
            // gross-vs-net can be reported separately later, but the default
            // dashboard shows net.
            ->where(function ($q) {
                $q->where('event_type', '!=', \App\Models\RecommendationEvent::TYPE_PURCHASE)
                  ->orWhereNull('reversed_at');
            })
            ->groupBy('recommendation_type', 'event_type')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! isset($out[$r->recommendation_type])) {
                $out[$r->recommendation_type] = [
                    'type' => $r->recommendation_type,
                    'impressions' => 0,
                    'clicks' => 0,
                    'add_to_cart' => 0,
                    'purchases' => 0,
                ];
            }
            $key = match ($r->event_type) {
                RecommendationEvent::TYPE_IMPRESSION  => 'impressions',
                RecommendationEvent::TYPE_CLICK       => 'clicks',
                RecommendationEvent::TYPE_ADD_TO_CART => 'add_to_cart',
                RecommendationEvent::TYPE_PURCHASE    => 'purchases',
                default => null,
            };
            if ($key !== null) {
                $out[$r->recommendation_type][$key] = (int) $r->c;
            }
        }

        foreach ($out as $type => $stats) {
            $imps = max(1, $stats['impressions']);
            $out[$type]['ctr']      = round($stats['clicks'] / $imps * 100, 2);
            $out[$type]['a2c_rate'] = round($stats['add_to_cart'] / $imps * 100, 2);
            $out[$type]['cv_rate']  = round($stats['purchases'] / $imps * 100, 2);
        }

        return array_values($out);
    }

    /**
     * Top-performing recommended products (by add_to_cart count) in the
     * lookback window. Aggregated; no per-customer data.
     */
    public function getTopProductsProperty(): array
    {
        $cutoff = now()->subDays($this->days);

        return DB::table('recommendation_events')
            ->join('products', 'products.id', '=', 'recommendation_events.recommended_product_id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('COUNT(CASE WHEN recommendation_events.event_type = "impression" THEN 1 END) as impressions'),
                DB::raw('COUNT(CASE WHEN recommendation_events.event_type = "click" THEN 1 END) as clicks'),
                DB::raw('COUNT(CASE WHEN recommendation_events.event_type = "add_to_cart" THEN 1 END) as add_to_cart')
            )
            ->where('recommendation_events.created_at', '>=', $cutoff)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('add_to_cart')
            ->limit(10)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
