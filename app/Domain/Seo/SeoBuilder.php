<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Str;

/**
 * Phase 10 — SEO builder.
 *
 * Composes per-page SEO payloads (title, description, canonical, OG/Twitter
 * fields, JSON-LD structured data) that controllers attach to the request
 * via $request->attributes->set('seo', $builder->forProduct($product)).
 *
 * Review-related structured data is computed from products.rating_avg +
 * products.rating_count which the ReviewService maintains (approved
 * reviews only per Phase 9 v9.0 + the v9.5 lazy-load fix).
 */
final class SeoBuilder
{
    /**
     * Marketplace homepage.
     * Emits Organization + WebSite JSON-LD.
     */
    public function forHome(): array
    {
        $url = (string) config('app.url');
        return [
            'title'       => (string) config('app.name'),
            'description' => (string) config('marketplace.seo_default_description', 'Multi-vendor marketplace for products and services.'),
            'canonical'   => $url,
            'og_type'     => 'website',
            'structured_data' => [
                [
                    '@context' => 'https://schema.org',
                    '@type'    => 'Organization',
                    'name'     => (string) config('app.name'),
                    'url'      => $url,
                    'logo'     => $url . '/logo.png',
                ],
                [
                    '@context'        => 'https://schema.org',
                    '@type'           => 'WebSite',
                    'name'            => (string) config('app.name'),
                    'url'             => $url,
                    'potentialAction' => [
                        '@type'       => 'SearchAction',
                        'target'      => $url . '/products?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    }

    /**
     * Product detail page.
     *
     * Emits Product JSON-LD with offers + (only when rating_count > 0)
     * aggregateRating, plus BreadcrumbList. The category fallback is
     * intentional: if the product has no category, the breadcrumb skips
     * that level rather than emitting an empty crumb.
     */
    public function forProduct(Product $product): array
    {
        $url       = url('/products/' . $product->slug);
        $title     = $product->name;
        $excerpt   = $this->excerpt((string) ($product->description ?? ''));
        $price     = number_format(((int) $product->price_minor) / 100, 2, '.', '');
        $currency  = $product->currency ?? config('marketplace.default_currency', 'KWD');
        $available = ($product->stock ?? 0) > 0 || ! $product->track_stock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $productLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $title,
            'description' => $excerpt,
            'sku'         => 'PROD-' . $product->id,
            'url'         => $url,
            'offers'      => [
                '@type'         => 'Offer',
                'url'           => $url,
                'price'         => $price,
                'priceCurrency' => $currency,
                'availability'  => $available,
            ],
        ];

        // Review/rating structured data — ONLY from approved reviews
        // (products.rating_count is maintained from approvedReviews()).
        if (($product->rating_count ?? 0) > 0 && ($product->rating_avg ?? 0) > 0) {
            $productLd['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $product->rating_avg,
                'reviewCount' => (int) $product->rating_count,
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        $breadcrumb = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $this->buildBreadcrumb($product),
        ];

        // OG image: first product image if present, otherwise null
        $ogImage = null;
        try {
            $firstImage = $product->images->first();
            if ($firstImage && ! empty($firstImage->url)) {
                $ogImage = (string) $firstImage->url;
            }
        } catch (\Throwable) {
            // images relation may not be loaded; that's fine
        }

        return [
            'title'       => $title,
            'description' => $excerpt,
            'canonical'   => $url,
            'og_type'     => 'product',
            'og_image'    => $ogImage,
            'structured_data' => [$productLd, $breadcrumb],
        ];
    }

    /**
     * Product listing / catalog page.
     */
    public function forProductListing(?Category $category = null): array
    {
        $url   = $category ? url('/products?category=' . $category->slug) : url('/products');
        $title = $category ? $category->name : 'All products';
        $desc  = $category
            ? "Browse {$category->name} products from verified vendors."
            : 'Browse products from verified vendors across the marketplace.';

        return [
            'title'       => $title,
            'description' => $desc,
            'canonical'   => $url,
            'og_type'     => 'website',
            'structured_data' => $category
                ? [[
                    '@context' => 'https://schema.org',
                    '@type'    => 'CollectionPage',
                    'name'     => $category->name,
                    'url'      => $url,
                ]]
                : null,
        ];
    }

    /**
     * Service listing.
     */
    public function forServiceListing(): array
    {
        $url = url('/services');
        return [
            'title'       => 'Services',
            'description' => 'Book services from verified providers.',
            'canonical'   => $url,
            'og_type'     => 'website',
        ];
    }

    /**
     * Service detail.
     * Services use the same Product table (type=service), so we emit a Service-typed JSON-LD.
     */
    public function forService(Product $service): array
    {
        $url     = url('/services/' . $service->slug);
        $title   = $service->name;
        $excerpt = $this->excerpt((string) ($service->description ?? ''));

        return [
            'title'       => $title,
            'description' => $excerpt,
            'canonical'   => $url,
            'og_type'     => 'website',
            'structured_data' => [[
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'name'        => $title,
                'description' => $excerpt,
                'url'         => $url,
                'offers'      => [
                    '@type'         => 'Offer',
                    'price'         => number_format(((int) $service->price_minor) / 100, 2, '.', ''),
                    'priceCurrency' => $service->currency ?? config('marketplace.default_currency', 'KWD'),
                ],
            ]],
        ];
    }

    /**
     * Deals/promotions page.
     */
    public function forDeals(): array
    {
        $url = url('/deals');
        return [
            'title'       => 'Deals',
            'description' => 'Current promotions and discounted products from marketplace vendors.',
            'canonical'   => $url,
            'og_type'     => 'website',
        ];
    }

    /* ─────────── helpers ─────────── */

    private function excerpt(string $raw, int $maxLength = 160): string
    {
        $clean = Str::of(strip_tags($raw))->squish();
        if ($clean->length() <= $maxLength) {
            return (string) $clean;
        }
        return $clean->substr(0, $maxLength - 1)->append('…')->value();
    }

    /**
     * @return list<array{@type:string,position:int,name:string,item:string}>
     */
    private function buildBreadcrumb(Product $product): array
    {
        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',    'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Products', 'item' => url('/products')],
        ];
        $pos = 3;
        try {
            if ($product->category) {
                $crumbs[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $product->category->name, 'item' => url('/products?category=' . $product->category->slug)];
            }
        } catch (\Throwable) {
            // category not loaded; skip the level
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $product->name, 'item' => url('/products/' . $product->slug)];
        return $crumbs;
    }
}
