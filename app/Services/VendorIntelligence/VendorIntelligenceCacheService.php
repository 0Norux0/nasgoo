<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11B.4 §5 §27 §37 — vendor-intelligence cache with per-vendor keys.
 *
 * §27 §37 CRITICAL: cache key includes vendor_id + locale — one vendor's
 * dashboard is NEVER served from another vendor's cache. This is
 * enforced by construction (the key template requires vendor_id).
 *
 * §27 invalidation triggers (called by observers / commands):
 *   - product created/updated/deleted    → flush(vendor)
 *   - stock changes                       → flush(vendor)
 *   - order status transitions            → flush(vendor)
 *   - vendor profile update                → flush(vendor)
 *   - admin threshold change               → flushAll()
 */
class VendorIntelligenceCacheService
{
    private const KEY_PREFIX = 'vi:v11b4';
    private const TTL = 900;  // 15 minutes

    public static function dashboardKey(int $vendorId, string $locale): string
    {
        return self::KEY_PREFIX . ":dash:{$vendorId}:{$locale}:v1";
    }

    public function rememberDashboard(int $vendorId, string $locale, \Closure $compute): array
    {
        try {
            return Cache::remember(self::dashboardKey($vendorId, $locale), self::TTL, $compute);
        } catch (\Throwable $e) {
            // v10.15-style defensive catch — if the cache layer fails, we
            // just compute freshly. Never let cache failure 500 the dashboard.
            \Log::warning('v11B.4 vendor intelligence cache failed (defensive catch)', [
                'error' => $e->getMessage(),
                'vendor_id' => $vendorId,
            ]);
            return $compute();
        }
    }

    public function flush(int $vendorId): void
    {
        try {
            foreach (['en', 'ar', 'ur'] as $locale) {
                Cache::forget(self::dashboardKey($vendorId, $locale));
            }
        } catch (\Throwable $e) {
            \Log::warning('v11B.4 flush() failed', ['vendor_id' => $vendorId, 'err' => $e->getMessage()]);
        }
    }

    public function flushAll(): void
    {
        try {
            Cache::flush();  // configuration change means we need to blow the cache
        } catch (\Throwable) {
            // ignore; next reads will just miss
        }
    }
}
