<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DealsController extends Controller
{
    /**
     * GET /deals — public deals listing.
     *
     * Returns every currently-usable promotion paired with up to
     * 3 sample products for display. The PromotionResolver computes
     * which product wins which promotion at render time on the
     * catalog pages; this listing is just a marketing surface.
     */
    public function index(): Response
    {
        $promotions = Promotion::usable()
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get()
            ->map(function (Promotion $p) {
                // Sample products targeted by this promotion (if any)
                $sampleProducts = collect();
                $targets = PromotionTarget::where('promotion_id', $p->id)->limit(3)->get();
                foreach ($targets as $t) {
                    if ($t->targetable_type === Product::class) {
                        $sampleProducts->push(
                            Product::query()
                                ->where('id', $t->targetable_id)
                                ->select(['id', 'slug', 'name', 'price_minor', 'currency'])
                                ->first()
                        );
                    }
                }
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'promotion_type' => $p->promotion_type,
                    'discount_type' => $p->discount_type,
                    'discount_value' => $p->discount_value,
                    'ends_at' => $p->ends_at?->toIso8601String(),
                    'sample_products' => $sampleProducts->filter()->values(),
                ];
            });

        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forDeals());
        return Inertia::render('Deals/Index', [
            'promotions' => $promotions,
        ]);
    }
}
