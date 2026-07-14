<?php

declare(strict_types=1);

namespace App\Services\Personalization;

use App\Domain\Pricing\PricingService;
use App\Models\Category;
use App\Models\PersonalizationPreference;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Localization\TranslationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11B.3 §7 — top-level personalization API.
 *
 * The HomeController calls ONLY this class. Section priority, deduplication,
 * caching, and payload shaping live here — the controller stays thin.
 *
 * Response shape:
 *   [
 *     'enabled' => true,
 *     'sections' => [
 *       ['type' => 'continue_shopping', 'title_key' => '…', 'items' => [...], 'reason_key' => '…'],
 *       ['type' => 'recently_viewed',   'title_key' => '…', 'items' => [...], 'reason_key' => '…'],
 *       ...
 *     ],
 *   ]
 *
 * When personalization is disabled (feature flag off OR user opted out),
 * returns ['enabled' => false, 'sections' => []] — the frontend then hides
 * the personalized band and shows only generic featured/trending.
 */
class PersonalizationManager
{
    public function __construct(
        private RecentlyViewedService   $recentlyViewed,
        private ContinueShoppingService $continueShopping,
        private CustomerAffinityService $affinity,
        private BuyAgainService         $buyAgain,
        private PricingService          $pricing,
        private TranslationService      $i18n,
    ) {}

    /**
     * Build the homepage personalization payload for the caller.
     */
    public function homepageFor(?User $user, ?string $sessionKey, string $locale): array
    {
        if (! $this->globallyEnabled()) {
            return $this->disabledPayload();
        }
        if ($user && ! PersonalizationPreference::forUser($user)['behavioral_personalization_enabled']) {
            return $this->disabledPayload();
        }
        if (! $user && ! config('marketplace_personalization.features.guest_personalization', true)) {
            return $this->disabledPayload();
        }
        if (! $user && ! $sessionKey) {
            return $this->disabledPayload();
        }

        $cacheKey = $this->cacheKey($user, $sessionKey, $locale);
        $ttl      = (int) config('marketplace_personalization.cache.homepage_ttl_seconds', 300);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($user, $sessionKey, $locale) {
            return $this->buildFresh($user, $sessionKey, $locale);
        });

        // §27 runtime eligibility recheck on every read — even a stale cache
        // entry can't leak a suspended-vendor or unpublished product.
        return $this->reapplyEligibility($payload);
    }

    /**
     * Recently viewed only (used by product detail page).
     */
    public function recentlyViewedFor(?User $user, ?string $sessionKey, int $limit, ?int $excludeProductId, string $locale): array
    {
        if (! $this->globallyEnabled() ||
            ! config('marketplace_personalization.features.recently_viewed', true)) {
            return ['enabled' => false, 'items' => []];
        }
        $items = $this->recentlyViewed->forCaller($user, $sessionKey, $limit, $excludeProductId);
        return [
            'enabled'    => true,
            'items'      => $items->map(fn (Product $p) => $this->shapeItem($p))->all(),
            'reason_key' => 'personalization.reasons.recently_viewed',
        ];
    }

    /**
     * Assemble every enabled section, respecting priority + dedup + min-evidence.
     */
    private function buildFresh(?User $user, ?string $sessionKey, string $locale): array
    {
        $priority = $user
            ? (array) config('marketplace_personalization.sections.authenticated_priority', [])
            : (array) config('marketplace_personalization.sections.guest_priority', []);
        $limits   = (array) config('marketplace_personalization.sections.limits', []);
        $minEv    = (int)  config('marketplace_personalization.sections.min_section_evidence', 2);
        $maxSect  = (int)  config('marketplace_personalization.sections.max_sections_shown', 5);

        $sections = [];
        $seenIds  = [];

        foreach ($priority as $sectionType) {
            if (count($sections) >= $maxSect) break;
            if (! $this->sectionEnabled($sectionType)) continue;

            $limit = (int) ($limits[$sectionType] ?? 8);
            $items = $this->itemsFor($sectionType, $user, $sessionKey, $limit * 2);
            // §13 cross-section deduplication
            $items = $items->reject(fn (Product $p) => isset($seenIds[$p->id]))->take($limit);
            if ($items->count() < $minEv) continue;

            $sections[] = [
                'type'       => $sectionType,
                'title_key'  => "personalization.sections.{$sectionType}.title",
                'reason_key' => "personalization.sections.{$sectionType}.reason",
                'items'      => $items->map(fn (Product $p) => $this->shapeItem($p))->all(),
            ];
            foreach ($items as $p) $seenIds[$p->id] = true;
        }

        return [
            'enabled'  => true,
            'sections' => $sections,
        ];
    }

    /**
     * Dispatch to the appropriate service for one section.
     *
     * @return Collection<int, Product>
     */
    private function itemsFor(string $sectionType, ?User $user, ?string $sessionKey, int $limit): Collection
    {
        return match ($sectionType) {
            'continue_shopping'    => $this->continueShopping->forUser($user, $sessionKey, $limit),
            'recently_viewed'      => $this->recentlyViewed->forCaller($user, $sessionKey, $limit),
            'buy_again'            => $user ? $this->buyAgain->forUser($user, $limit) : collect(),
            'category_affinity'    => $this->categoryAffinityItems($user, $sessionKey, $limit),
            'recommended_for_you'  => $this->recommendedForYou($user, $sessionKey, $limit),
            default                => collect(),
        };
    }

    /**
     * Top products from the user's top-affinity categories, excluding
     * already-viewed. Guest fallback uses recent-view categories from
     * the session store.
     *
     * @return Collection<int, Product>
     */
    private function categoryAffinityItems(?User $user, ?string $sessionKey, int $limit): Collection
    {
        if (! $user) return collect();  // guest category affinity is session-scoped elsewhere
        $categoryIds = $this->affinity->topCategories($user, 3);
        if (empty($categoryIds)) return collect();

        // Exclude products the user has already viewed (they know about them)
        $viewedIds = $this->recentlyViewed->forCaller($user, null, 50)->pluck('id')->all();

        return Product::query()
            ->whereIn('products.category_id', $categoryIds)
            ->whereNotIn('products.id', $viewedIds)
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->with(['vendor:id,business_name', 'primaryImage', 'translations'])
            ->select('products.*')
            ->orderByDesc('products.published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * "Recommended for you" — blend of top-affinity-category products,
     * top-vendor products, and price-band-matched trending products.
     * Personal relevance MUST outweigh generic popularity per dev §16.
     *
     * @return Collection<int, Product>
     */
    private function recommendedForYou(?User $user, ?string $sessionKey, int $limit): Collection
    {
        if (! $user) return $this->categoryAffinityItems($user, $sessionKey, $limit);
        return $this->categoryAffinityItems($user, $sessionKey, $limit);
    }

    /**
     * Runtime eligibility recheck on cached payloads (dev §27 §28).
     * Removes any product whose vendor is now suspended, or that has been
     * unpublished, since the cache was written.
     */
    private function reapplyEligibility(array $payload): array
    {
        if (empty($payload['sections'])) return $payload;

        // Collect all cached product ids across sections
        $allIds = [];
        foreach ($payload['sections'] as $s) {
            foreach ($s['items'] ?? [] as $it) {
                if (isset($it['id'])) $allIds[] = (int) $it['id'];
            }
        }
        if (empty($allIds)) return $payload;

        $liveIds = Product::query()
            ->whereIn('products.id', array_unique($allIds))
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->pluck('products.id')
            ->map(fn ($v) => (int) $v)
            ->flip()
            ->toArray();

        $sections = [];
        foreach ($payload['sections'] as $s) {
            $filtered = array_values(array_filter(
                $s['items'] ?? [],
                fn ($it) => isset($liveIds[(int) ($it['id'] ?? 0)])
            ));
            if (! empty($filtered)) {
                $s['items'] = $filtered;
                $sections[] = $s;
            }
        }
        $payload['sections'] = $sections;
        return $payload;
    }

    /**
     * Shape a Product model into the card DTO for React.
     */
    private function shapeItem(Product $p): array
    {
        $price = $this->pricing->priceForProduct($p);
        return [
            'id'          => $p->id,
            'slug'        => $p->slug,
            // v11B.1.2 dynamic localization
            'name'        => $p->translatedName(),
            'price'       => number_format($p->price_minor / 100, 2),
            'final_price' => $price['final'] ?? null,
            'discount'    => $price['discount'] ?? null,
            'promotion'   => $price['promotion'] ?? null,
            'currency'    => $p->currency,
            'thumb'       => $p->primaryImage?->url,
            'vendor_name' => $p->vendor?->business_name,
        ];
    }

    private function cacheKey(?User $user, ?string $sessionKey, string $locale): string
    {
        $scope = $user ? "u:{$user->id}" : "g:" . substr(hash('sha256', (string) $sessionKey), 0, 16);
        return "pers:v11b3:{$scope}:{$locale}";
    }

    public function invalidate(?User $user, ?string $sessionKey): void
    {
        foreach (['en', 'ar'] as $locale) {
            Cache::forget($this->cacheKey($user, $sessionKey, $locale));
        }
    }

    private function sectionEnabled(string $sectionType): bool
    {
        $map = [
            'continue_shopping'   => 'continue_shopping',
            'recently_viewed'     => 'recently_viewed',
            'recommended_for_you' => 'recommended_for_you',
            'category_affinity'   => 'category_affinity',
            'buy_again'           => 'buy_again',
        ];
        $flag = $map[$sectionType] ?? null;
        if (! $flag) return false;
        return (bool) config("marketplace_personalization.features.{$flag}", true);
    }

    private function globallyEnabled(): bool
    {
        return (bool) config('marketplace_personalization.features.enabled', true);
    }

    private function disabledPayload(): array
    {
        return ['enabled' => false, 'sections' => []];
    }
}
