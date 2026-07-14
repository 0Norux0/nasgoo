<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Search\DidYouMeanService;
use App\Services\Search\MarketplaceSearchService;
use App\Services\Search\QueryNormalizer;
use App\Services\Search\SearchAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogController extends Controller
{
    /**
     * GET /products — public catalog browse with v11B.1 smart search.
     *
     * Phase 11B.1 §4 §5 §13 §14 §15 §16 §17 §18 §19 §20 — relevance ranking,
     * dynamic filters, faceted counts, did-you-mean, search analytics.
     *
     * Supported query params (additive — all v11A.5 params still work):
     *   - ?q=…                    (search term; uses MarketplaceSearchService when smart_search on)
     *   - ?category=electronics    (single category slug)
     *   - ?vendor=acme            (vendor slug)
     *   - ?price_min=10           (KWD)
     *   - ?price_max=100
     *   - ?rating_min=4           (1..5)
     *   - ?in_stock=1             (true → only in-stock items)
     *   - ?on_sale=1              (true → only items with featured/promo)
     *   - ?sort=relevance|newest|price_asc|price_desc|rating|popular|best_selling|featured
     *           (default: relevance when q set, else newest)
     *
     * Per dev §15: URL query parameters remain the source of truth.
     * Per dev §31: server-side filtering, no full-table model loading.
     */
    public function index(
        Request $request,
        MarketplaceSearchService $search,
        DidYouMeanService $dym,
        SearchAnalyticsService $analytics,
    ): Response
    {
        $locale = app()->getLocale();

        // Normalize and validate inputs (defends §28 unbounded input)
        $rawQ          = $request->string('q')->toString();
        $q             = QueryNormalizer::normalize($rawQ);
        $categorySlug  = $request->string('category')->toString();
        $vendorSlug    = $request->string('vendor')->toString();
        $priceMin      = max(0, (int) $request->integer('price_min', 0));
        $priceMax      = max(0, (int) $request->integer('price_max', 0));
        $ratingMin     = (int) $request->integer('rating_min', 0);
        $ratingMin     = max(0, min(5, $ratingMin));
        $inStockOnly   = $request->boolean('in_stock');
        $onSaleOnly    = $request->boolean('on_sale');
        $sort          = $request->string('sort', $q !== '' ? 'relevance' : 'newest')->toString();

        $allowedSorts = ['relevance', 'newest', 'price_asc', 'price_desc', 'rating', 'popular', 'best_selling', 'featured'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = $q !== '' ? 'relevance' : 'newest';
        }

        // Build the base query: use MarketplaceSearchService when smart-search ON + query set
        $smartOn = (bool) config('marketplace_search.features.smart_search_enabled', true);

        $query = null;
        if ($smartOn && $q !== '') {
            $query = $search->products($rawQ, $locale);
        }

        if ($query === null) {
            // Browse mode OR smart search disabled OR query too short
            $query = Product::query()
                ->published()
                ->where('type', '!=', Product::TYPE_SERVICE);

            if ($q !== '') {
                // Legacy LIKE search (used when smart search is disabled)
                $needle = '%' . str_replace('%', '\\%', mb_strtolower($q)) . '%';
                $query->whereRaw('LOWER(name) LIKE ?', [$needle]);
            }
        }

        $query->with([
            'vendor:id,business_name,slug,status',
            'category:id,name,slug,name_translations',
            'primaryImage:id,product_id,path',
        ]);

        // Apply filters
        $activeCategory = null;
        if ($categorySlug !== '') {
            $activeCategory = Category::where('slug', $categorySlug)->where('is_active', true)->first();
            if ($activeCategory) {
                $query->where('products.category_id', $activeCategory->id);
            }
        }

        $activeVendor = null;
        if ($vendorSlug !== '') {
            $activeVendor = Vendor::where('slug', $vendorSlug)->first();
            if ($activeVendor) {
                $query->where('products.vendor_id', $activeVendor->id);
            }
        }

        if ($priceMin > 0) {
            $query->where('products.price_minor', '>=', $priceMin * 100);
        }
        if ($priceMax > 0) {
            $query->where('products.price_minor', '<=', $priceMax * 100);
        }
        if ($ratingMin > 0) {
            $query->where('products.rating_avg', '>=', $ratingMin);
        }
        if ($inStockOnly) {
            $query->where(function ($q) {
                $q->where('products.track_stock', false)->orWhere('products.stock', '>', 0);
            });
        }
        if ($onSaleOnly) {
            $query->where('products.featured', true)
                  ->where(function ($q) {
                      $q->whereNull('products.featured_until')
                        ->orWhere('products.featured_until', '>=', now());
                  });
        }

        // Apply explicit sort (overrides relevance ordering when not 'relevance')
        if ($sort !== 'relevance' || $q === '') {
            $query->reorder();
            $query = match ($sort) {
                'price_asc'    => $query->orderBy('products.price_minor'),
                'price_desc'   => $query->orderByDesc('products.price_minor'),
                'rating'       => $query->orderByDesc('products.rating_avg')->orderByDesc('products.rating_count'),
                'popular'      => $query->orderByDesc('products.views_count'),
                'best_selling' => $query->orderByDesc('products.sales_count'),
                'featured'     => $query->orderByDesc('products.featured')->orderByDesc('products.published_at'),
                default        => $query->orderByDesc('products.published_at'),
            };
        }

        $perPage  = (int) config('marketplace_search.limits.catalog_per_page', 24);
        $products = $query->paginate($perPage)->withQueryString();

        // Record analytics — fire-and-forget; failures must not break the response
        if ($q !== '') {
            try {
                $analytics->recordSearch($rawQ, $locale, $products->total(), $request->user());
            } catch (\Throwable $e) {
                \Log::warning('v11B.1 analytics recordSearch failed', ['error' => $e->getMessage()]);
            }
        }

        // Did you mean? — only when query set + few/no results
        $didYouMean = null;
        if ($q !== '' && $products->total() < 3) {
            try {
                $didYouMean = $dym->suggest($rawQ, $locale);
            } catch (\Throwable) {
                $didYouMean = null;
            }
        }

        // Pricing pipeline (unchanged Phase 10 v10.8 — bulk promo lookup)
        $pricing = app(\App\Domain\Pricing\PricingService::class);
        $priced  = $pricing->priceForProducts($products->getCollection());

        // Facet counts
        $facets = $this->buildFacets($activeCategory, $activeVendor, $q, $locale);

        // SEO (unchanged Phase 10)
        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forProductListing($activeCategory));

        return Inertia::render('Catalog/Index', [
            'products' => $products->through(function (Product $p) use ($priced) {
                $row = $priced[$p->id] ?? null;
                return [
                    'id'           => $p->id,
                    'slug'         => $p->slug,
                    'name'         => $p->translatedName(),
                    'price'        => number_format($p->price_minor / 100, 2),
                    'currency'     => $p->currency,
                    'compare_at'   => $p->compare_at_price_minor
                        ? number_format($p->compare_at_price_minor / 100, 2)
                        : null,
                    'thumb'        => $p->primaryImage?->url,
                    'featured'     => (bool) $p->featured,
                    'vendor_name'  => $p->vendor?->business_name,
                    'vendor_slug'  => $p->vendor?->slug,
                    'category'     => $p->category?->translatedName(),
                    'rating_avg'   => $p->rating_avg ? round((float) $p->rating_avg, 1) : null,
                    'rating_count' => (int) ($p->rating_count ?? 0),
                    'final_price'  => $row['final'] ?? null,
                    'discount'     => $row['discount'] ?? null,
                    'promotion'    => $row['promotion'] ?? null,
                ];
            }),
            'categories' => Category::where('is_active', true)
                ->whereNull('parent_id')
                ->withCount(['products as published_count' => fn ($q) => $q->where('status', Product::STATUS_PUBLISHED)])
                ->orderBy('position')
                ->get(['id', 'slug', 'name', 'name_translations'])
                ->map(fn ($c) => [
                    'slug'  => $c->slug,
                    'name'  => $c->translatedName(),
                    'count' => $c->published_count,
                ]),
            'filters' => [
                'q'          => $rawQ ?: null,
                'category'   => $categorySlug ?: null,
                'vendor'     => $vendorSlug ?: null,
                'price_min'  => $priceMin > 0 ? $priceMin : null,
                'price_max'  => $priceMax > 0 ? $priceMax : null,
                'rating_min' => $ratingMin > 0 ? $ratingMin : null,
                'in_stock'   => $inStockOnly ?: null,
                'on_sale'    => $onSaleOnly ?: null,
                'sort'       => $sort,
            ],
            'active_category' => $activeCategory ? [
                'slug' => $activeCategory->slug,
                'name' => $activeCategory->translatedName(),
            ] : null,
            'active_vendor' => $activeVendor ? [
                'slug' => $activeVendor->slug,
                'name' => $activeVendor->business_name,
            ] : null,
            'facets'      => $facets,
            'did_you_mean'=> $didYouMean,
        ]);
    }

    /**
     * Compute faceted counts for the current scope. Cached briefly
     * to avoid per-request DB hammering. Per dev §14 §24.
     */
    private function buildFacets(?Category $activeCategory, ?Vendor $activeVendor, string $q, string $locale): array
    {
        if (! (bool) config('marketplace_search.features.facets_enabled', true)) {
            return [];
        }

        $cacheKey = sprintf(
            'marketplace:search:facets:v1:%s:%s:%s:%s',
            $locale,
            $activeCategory?->slug ?: 'all',
            $activeVendor?->slug ?: 'all',
            md5($q)
        );

        return Cache::remember(
            $cacheKey,
            (int) config('marketplace_search.cache.facets_ttl_seconds', 60),
            function () use ($activeCategory, $activeVendor, $q): array {
                $base = Product::query()
                    ->published()
                    ->where('type', '!=', Product::TYPE_SERVICE);

                if ($activeCategory) {
                    $base->where('category_id', $activeCategory->id);
                }
                if ($activeVendor) {
                    $base->where('vendor_id', $activeVendor->id);
                }
                if ($q !== '') {
                    $needle = '%' . str_replace('%', '\\%', mb_strtolower($q)) . '%';
                    $base->whereRaw('LOWER(name) LIKE ?', [$needle]);
                }

                $inStock = (clone $base)
                    ->where(function ($q) {
                        $q->where('track_stock', false)->orWhere('stock', '>', 0);
                    })
                    ->count();

                $outOfStock = (clone $base)
                    ->where('track_stock', true)
                    ->where('stock', '<=', 0)
                    ->count();

                $onSale = (clone $base)
                    ->where('featured', true)
                    ->where(function ($q) {
                        $q->whereNull('featured_until')->orWhere('featured_until', '>=', now());
                    })
                    ->count();

                $rating4plus = (clone $base)->where('rating_avg', '>=', 4)->count();
                $rating3plus = (clone $base)->where('rating_avg', '>=', 3)->count();

                return [
                    'in_stock'     => $inStock,
                    'out_of_stock' => $outOfStock,
                    'on_sale'      => $onSale,
                    'rating_4plus' => $rating4plus,
                    'rating_3plus' => $rating3plus,
                ];
            }
        );
    }


    /**
     * GET /products/{slug} — public product detail page.
     *
     * Phase 8 v8.1 — services have a dedicated detail surface with the
     * booking widget at /services/{slug}. If a service-type slug is hit
     * via /products/{slug} (eg. an old bookmark from Phase 8.0), redirect
     * to the service page instead of rendering a "product" detail with
     * no checkout/booking story.
     *
     * Phase 8 v8.7 — return type fixed. The v8.1 patch wrongly typed this
     * as Symfony\Component\HttpFoundation\Response thinking it was a
     * superclass of both branches. But Inertia\Response does NOT extend
     * Symfony\HttpFoundation\Response (it implements Responsable instead),
     * so returning Inertia::render() under that type raised:
     *
     *   TypeError: Return value must be of type Symfony\...\Response,
     *              Inertia\Response returned
     *
     * Same bug class as the v5.3 CheckoutController::show fix. The right
     * type for "either an Inertia page OR a redirect" is a union of the
     * two concrete types.
     */
    public function show(string $slug): Response|RedirectResponse
    {
        // Phase 8 v8.1 — service-aware redirect. Cheap pre-check using a
        // single column lookup; bails out before the heavy with() load.
        $type = Product::where('slug', $slug)->value('type');
        if ($type === Product::TYPE_SERVICE) {
            return redirect("/services/{$slug}", 301);
        }

        $product = Product::query()
            ->published()
            ->where('type', '!=', Product::TYPE_SERVICE)
            ->with([
                'vendor:id,business_name,slug,rating_avg,rating_count',
                'category:id,name,slug',
                'images',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('position'),
                'attributeValues.attribute:id,name,slug',
                // Phase 5 — load approved reviews + their authors for the
                // reviews block. Order is controllable via ?reviews_sort=.
                'approvedReviews' => fn ($q) => $q->latest()->with('user:id,name'),
                // Phase 7 — load active customization fields (sorted) so the
                // detail page can render the customer-facing customization form.
                'activeCustomizationFields',
            ])
            ->where('slug', $slug)
            ->first();

        if (! $product) {
            throw new NotFoundHttpException();
        }

        // Best-effort view counter — won't block rendering on failure
        try {
            $product->increment('views_count');
        } catch (\Throwable) {}

        // Phase 11B.3 §8 — expose product id for the RecordProductView
        // middleware. Middleware runs post-response so it only records
        // a view for a 200; if we throw above, no attribute is set and
        // no view is recorded.
        request()->attributes->set('viewed_product_id', $product->id);

        // Phase 10 — per-page SEO (title/description/canonical/JSON-LD).
        // SeoBuilder reads from the already-eager-loaded product (images,
        // category, rating columns) so no extra queries are issued.
        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forProduct($product));

        // Phase 10 v10.8 — promotion-aware pricing. Same PricingService the
        // listing uses, so detail/listing always agree.
        $priceDto = app(\App\Domain\Pricing\PricingService::class)->priceForProduct($product);

        return Inertia::render('Catalog/Show', [
            'product' => [
                'id'                => $product->id,
                'slug'              => $product->slug,
                'name'              => $product->translatedName(),
                'short_description' => $product->translatedShortDescription(),
                'description'       => $product->translatedDescription(),
                'type'              => $product->type,
                'price'             => number_format($product->price_minor / 100, 2),
                // Phase 11B.2 — raw minor units needed by FBT for accurate
                // combined-total arithmetic (locale-agnostic, no FP drift)
                'price_minor'       => (int) $product->price_minor,
                'currency'          => $product->currency,
                'compare_at'        => $product->compare_at_price_minor
                    ? number_format($product->compare_at_price_minor / 100, 2)
                    : null,
                // Phase 10 v10.8 — promotion-aware fields (null when no promotion)
                'final_price'       => $priceDto['final'],
                'discount'          => $priceDto['discount'],
                'promotion'         => $priceDto['promotion'],
                'stock'             => $product->availableStock(),
                'track_stock'       => $product->track_stock,
                'rating_avg'        => (float) $product->rating_avg,
                'rating_count'      => $product->rating_count,
                'featured'          => $product->featured,
                'images'            => $product->images->map(fn ($i) => [
                    'id'         => $i->id,
                    'path'       => $i->path,
                    'url'        => $i->url,
                    'alt'        => $i->alt_text,
                    'is_primary' => $i->is_primary,
                ]),
                'variants'          => $product->variants->map(fn ($v) => [
                    'id'         => $v->id,
                    'name'       => $v->name,
                    'sku'        => $v->sku,
                    'price'      => number_format($v->price_minor / 100, 2),
                    'stock'      => $v->stock,
                    'attributes' => $v->attribute_values,
                ]),
                'attributes' => $product->attributeValues
                    ->groupBy(fn ($v) => $v->attribute->name)
                    ->map(fn ($group, $attrName) => [
                        'name'   => $attrName,
                        'values' => $group->pluck('value'),
                    ])
                    ->values(),
            ],
            'vendor' => $product->vendor ? [
                'business_name' => $product->vendor->business_name,
                'slug'          => $product->vendor->slug,
                'rating_avg'    => (float) $product->vendor->rating_avg,
                'rating_count'  => $product->vendor->rating_count,
            ] : null,
            'category' => $product->category ? [
                'slug' => $product->category->slug,
                'name' => $product->category->translatedName(),
            ] : null,

            // Phase 5 — is this product in the current user's wishlist?
            'is_wishlisted' => auth()->check()
                ? \App\Models\Wishlist::where('user_id', auth()->id())->where('product_id', $product->id)->exists()
                : false,

            // Phase 5 — reviews block + customer's review eligibility
            'reviews' => [
                'rating_avg'   => (float) $product->rating_avg,
                'rating_count' => (int) $product->rating_count,
                'items' => $product->approvedReviews->sortByDesc(function ($r) {
                    $sort = request('reviews_sort');
                    return match ($sort) {
                        'highest' => $r->rating,
                        'lowest'  => -$r->rating,
                        default   => $r->created_at?->timestamp,
                    };
                })->values()->map(fn ($r) => [
                    'id'                   => $r->id,
                    'rating'               => $r->rating,
                    'title'                => $r->title,
                    'body'                 => $r->body,
                    'author_name'          => $r->user?->name,
                    'is_verified_purchase' => $r->is_verified_purchase,
                    'created_at'           => $r->created_at?->toDateTimeString(),
                ]),
                // For the "Write a review" CTA on the page: which delivered
                // order_items of this product (for the current user) haven't
                // been reviewed yet?
                'reviewable_purchases' => optional(auth()->user(), function ($user) use ($product) {
                    return \App\Models\OrderItem::query()
                        ->where('product_id', $product->id)
                        ->whereHas('order', fn ($q) => $q
                            ->where('user_id', $user->id)
                            ->whereNotNull('delivered_at'))
                        ->whereDoesntHave('reviews', fn ($q) => $q->where('user_id', $user->id))
                        ->get(['id', 'order_id'])
                        ->map(fn ($i) => ['order_item_id' => $i->id, 'order_id' => $i->order_id])
                        ->values();
                }) ?? collect(),
            ],

            // Phase 7 — active customization fields for the product detail
            // page. Empty array unless type=custom. The shape matches the
            // CustomizationFieldDef interface in the React component.
            'customization_fields' => $product->isCustomizable()
                ? $product->activeCustomizationFields->map(fn ($f) => [
                    'id'                 => $f->id,
                    'key'                => $f->key,
                    'label'              => $f->label,
                    'type'               => $f->type,
                    'required'           => $f->required,
                    'sort_order'         => $f->sort_order,
                    'allowed_file_types' => $f->allowed_file_types,
                    'max_file_size_kb'   => $f->max_file_size_kb,
                    'max_text_length'    => $f->max_text_length,
                    'extra_fee_minor'    => $f->extra_fee_minor,
                    'placeholder'        => $f->placeholder,
                    'helper_text'        => $f->helper_text,
                    'options'            => $f->options,
                ])->values()
                : collect(),

            // Phase 11B.2 — 3 recommendation sections per product detail.
            // The RecommendationManager applies its own caching layer (by
            // product+locale+limit) so this controller call resolves quickly
            // even on uncached requests. Feature flags can disable any
            // section without breaking the page — disabled sections return
            // ['enabled' => false, 'items' => [], …].
            'recommendations' => [
                'similar' => app(\App\Services\Recommendations\RecommendationManager::class)
                    ->similarProducts($product, (int) config('marketplace_recommendations.limits.similar_products', 8)),
                'frequently_bought' => app(\App\Services\Recommendations\RecommendationManager::class)
                    ->frequentlyBoughtTogether($product, (int) config('marketplace_recommendations.limits.frequently_bought', 4)),
                'customers_also_bought' => app(\App\Services\Recommendations\RecommendationManager::class)
                    ->customersAlsoBought($product, (int) config('marketplace_recommendations.limits.customers_also_bought', 8)),
            ],
        ]);
    }
}
