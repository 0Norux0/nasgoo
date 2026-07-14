<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Personalization\PersonalizationManager;
use App\Services\Personalization\RecentlyViewedService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 11B.3 §8 — record a product view for personalization.
 *
 * Runs AFTER the controller so we only record actual 200 responses.
 * A 404 on the product page must not create a view row (dev §5
 * "no permanent tracking without policy support").
 *
 * The middleware infers the product from the route parameter 'slug' or
 * 'product' if present; caller controllers set an attribute
 * `viewed_product_id` to be explicit.
 */
class RecordProductView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($response->getStatusCode() !== 200) return $response;

            $productId = (int) ($request->attributes->get('viewed_product_id') ?? 0);
            if ($productId <= 0) return $response;

            $user       = $request->user();
            $sessionKey = $user ? null : $request->session()->getId();
            $locale     = app()->getLocale();

            app(RecentlyViewedService::class)->record(
                $user, $sessionKey, $productId, $locale,
                $this->deviceCategory($request)
            );

            // Invalidate the personalization cache so the next homepage
            // render reflects the new view.
            app(PersonalizationManager::class)->invalidate($user, $sessionKey);
        } catch (\Throwable $e) {
            \Log::warning('v11B.3 product-view record failed (defensive catch)', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function deviceCategory(Request $request): ?string
    {
        $ua = strtolower((string) $request->header('User-Agent', ''));
        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        return 'desktop';
    }
}
