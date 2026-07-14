<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 10 — Dynamic XML sitemap.
 *
 * Includes:
 *   - homepage
 *   - /products  (catalog landing)
 *   - /services (service catalog landing)
 *   - /deals    (public promotions page)
 *   - every published product (type != 'service')
 *   - every published service (type == 'service')
 *   - every category that contains at least one published product or service
 *
 * Explicitly excludes (NOT in the URL set):
 *   - admin/* (Filament + /admin/reports)
 *   - vendor/* (dashboard + reports + everything vendor-side)
 *   - customer dashboards (orders, bookings, tickets, account)
 *   - checkout, cart
 *   - login/register/password reset
 *   - unpublished/draft/suspended/archived products and services
 *
 * lastmod is set from updated_at where available. Schema:
 * https://www.sitemaps.org/protocol.html
 */
class SitemapController extends Controller
{
    public function index(Request $request): Response
    {
        $urls = [];

        // 1. Static public surfaces
        $urls[] = ['loc' => url('/'),         'changefreq' => 'daily',   'priority' => '1.0'];
        $urls[] = ['loc' => url('/products'), 'changefreq' => 'daily',   'priority' => '0.9'];
        $urls[] = ['loc' => url('/services'), 'changefreq' => 'daily',   'priority' => '0.9'];
        $urls[] = ['loc' => url('/deals'),    'changefreq' => 'daily',   'priority' => '0.8'];

        // 2. Categories that contain at least one published product/service.
        //    We don't want to expose empty category pages to crawlers.
        Category::query()
            ->whereHas('products', fn ($q) => $q->where('status', Product::STATUS_PUBLISHED))
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$urls) {
                foreach ($chunk as $cat) {
                    $urls[] = [
                        'loc'        => url('/products?category=' . $cat->slug),
                        'lastmod'    => $cat->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
            });

        // 3. Published products (type != service)
        Product::query()
            ->where('status', Product::STATUS_PUBLISHED)
            ->where('type', '!=', Product::TYPE_SERVICE)
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$urls) {
                foreach ($chunk as $p) {
                    $urls[] = [
                        'loc'        => url('/products/' . $p->slug),
                        'lastmod'    => $p->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                    ];
                }
            });

        // 4. Published services (type == service)
        Product::query()
            ->where('status', Product::STATUS_PUBLISHED)
            ->where('type', Product::TYPE_SERVICE)
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$urls) {
                foreach ($chunk as $s) {
                    $urls[] = [
                        'loc'        => url('/services/' . $s->slug),
                        'lastmod'    => $s->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            if (! empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . ($u['changefreq'] ?? 'weekly') . "</changefreq>\n";
            $xml .= '    <priority>' . ($u['priority'] ?? '0.5') . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
