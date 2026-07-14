<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forHome());

        // Phase 11B.3 §7 §11 §12 §22 §27 — personalized homepage payload.
        // Delegated to PersonalizationManager which:
        //   - respects feature flag (marketplace_personalization.features.enabled)
        //   - respects per-user opt-out (PersonalizationPreference)
        //   - caches per-user or per-guest-session (isolated cache keys)
        //   - runtime-eligibility-rechecks every cached product
        //   - honors section priority + cross-section deduplication
        // When the manager returns enabled=false, the storefront hides the
        // personalized band entirely and shows only the generic featured
        // section (preserving pre-v11B.3 behavior — no regression).
        $user       = $request->user();
        $sessionKey = $user ? null : $request->session()->getId();
        $personalization = app(\App\Services\Personalization\PersonalizationManager::class)
            ->homepageFor($user, $sessionKey, app()->getLocale());

        return Inertia::render('Welcome', [
            'phase'  => 'Phase 3 — Product Marketplace / Catalog',
            'personalization' => $personalization,  // v11B.3
            // Phase 10 v10.14 §3 PERFORMANCE — cache the health-probe block
            // for 30 seconds. Pre-v10.14 every public homepage render ran
            // four synchronous probes including checkMeilisearch() which has
            // a 2-second curl timeout. If Meilisearch was unreachable (a
            // common dev-env case), the homepage was slow by ~2s per render.
            // Caching for 30s collapses this to at most 1 probe per 30s
            // window. The badges' UX tolerates 30s staleness fine.
            //
            // Phase 10 v10.15 — defensive wrap. If the cache driver itself
            // throws (CACHE_STORE=redis with Redis unreachable, db cache
            // table missing, file-cache permission issue), fall back to
            // direct inline probes so / always renders. The customer
            // post-login redirect goes to /; if / 500s the user sees what
            // looks like a broken login. Defensive fallback turns a
            // worst-case 500 into a slow-but-functional page.
            'health' => (function () {
                try {
                    return \Illuminate\Support\Facades\Cache::remember(
                        'marketplace:homepage_health:v1',
                        now()->addSeconds(30),
                        fn () => [
                            'database'    => $this->checkDatabase(),
                            'redis'       => $this->checkRedis(),
                            'meilisearch' => $this->checkMeilisearch(),
                            'storage'     => $this->checkStorage(),
                        ]
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'homepage health cache failed (Phase 10 v10.15 defensive catch)',
                        ['message' => $e->getMessage()]
                    );
                    return [
                        'database'    => $this->checkDatabase(),
                        'redis'       => $this->checkRedis(),
                        'meilisearch' => $this->checkMeilisearch(),
                        'storage'     => $this->checkStorage(),
                    ];
                }
            })(),
            // Phase 3 — featured products preview (max 8). Empty array if catalog is empty.
            // Phase 10 v10.8 — promotion-aware pricing applied uniformly.
            'featured_products' => (function () {
                $featured = Product::query()
                    ->published()
                    ->featured()
                    ->with(['primaryImage:id,product_id,path', 'vendor:id,business_name'])
                    ->orderByDesc('published_at')
                    ->limit(8)
                    ->get();
                $priced = app(\App\Domain\Pricing\PricingService::class)->priceForProducts($featured);
                return $featured->map(function (Product $p) use ($priced) {
                    $row = $priced[$p->id] ?? null;
                    return [
                        'slug'        => $p->slug,
                        // v11A.5 — localize product name; falls back to canonical English when ar missing
                        'name'        => $p->translatedName(),
                        'price'       => number_format($p->price_minor / 100, 2),
                        'currency'    => $p->currency,
                        'thumb'       => $p->primaryImage?->url,
                        'vendor_name' => $p->vendor?->business_name,
                        // Phase 10 v10.8 — promotion fields (null when none applies)
                        'final_price' => $row['final'] ?? null,
                        'discount'    => $row['discount'] ?? null,
                        'promotion'   => $row['promotion'] ?? null,
                    ];
                });
            })(),
        ]);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $pong = Redis::ping();
            return $pong === '+PONG' || $pong === true || $pong === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkMeilisearch(): bool
    {
        if (! function_exists('curl_init')) {
            return false;
        }
        try {
            $host = env('MEILISEARCH_HOST', 'http://meilisearch:7700');
            $ch = curl_init(rtrim($host, '/').'/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            return Storage::disk(config('filesystems.default'))->exists('/');
        } catch (\Throwable) {
            return true;
        }
    }
}
