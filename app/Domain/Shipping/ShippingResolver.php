<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;

/**
 * Phase 5 — shipping method resolver.
 *
 * Given an address (country + optional region) and cart subtotal, returns
 * the list of available shipping methods. The first matching zone (lowest
 * `position`) wins; methods are filtered by their type rules
 * (min_subtotal_minor for free, max_weight_grams optional).
 */
final class ShippingResolver
{
    /**
     * Return all active methods available for the given shipping context.
     *
     * @return \Illuminate\Support\Collection<int, ShippingMethod>
     */
    public function availableFor(string $countryCode, ?string $region, int $subtotalMinor, ?int $totalWeightGrams = null): \Illuminate\Support\Collection
    {
        $zone = $this->resolveZone($countryCode, $region);
        if (! $zone) {
            // Fall back to global zone (countries=["*"]) if any admin has set one up
            $zone = ShippingZone::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereJsonContains('countries', '*');
                })
                ->orderBy('position')
                ->first();
        }

        if (! $zone) {
            return collect();
        }

        return $zone->activeMethods()
            ->get()
            ->filter(fn (ShippingMethod $m) => $m->isEligibleFor($subtotalMinor, $totalWeightGrams))
            ->values();
    }

    /**
     * Find the most specific active zone that covers the given country/region.
     * Region-specific zones (regions != null) take priority over country-wide
     * zones (regions == null).
     */
    public function resolveZone(string $countryCode, ?string $region = null): ?ShippingZone
    {
        $candidates = ShippingZone::query()
            ->where('is_active', true)
            ->whereJsonContains('countries', strtoupper($countryCode))
            ->orderByRaw('CASE WHEN regions IS NULL THEN 1 ELSE 0 END')  // region-specific first
            ->orderBy('position')
            ->get();

        foreach ($candidates as $zone) {
            if ($zone->covers($countryCode, $region)) {
                return $zone;
            }
        }
        return null;
    }
}
