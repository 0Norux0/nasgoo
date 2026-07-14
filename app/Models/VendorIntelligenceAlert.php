<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.4 §32 — vendor intelligence alert with lifecycle constants.
 *
 * Lifecycle: active → dismissed | snoozed | resolved | expired
 * Duplicate active alerts prevented by service layer (via_uniqness_idx
 * supports the lookup but doesn't enforce uniqueness — that would require
 * a partial unique index which isn't portable across drivers).
 */
class VendorIntelligenceAlert extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'evidence'    => 'array',
        'resolved_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    // Alert types (§7)
    public const TYPE_OUT_OF_STOCK               = 'out_of_stock';
    public const TYPE_LOW_STOCK                  = 'low_stock';
    public const TYPE_FAST_MOVING_LOW_STOCK      = 'fast_moving_low_stock';
    public const TYPE_SLOW_MOVING                = 'slow_moving';
    public const TYPE_STAGNANT                   = 'stagnant';
    public const TYPE_NO_STOCK_TRACKING          = 'no_stock_tracking';
    public const TYPE_MISSING_ARABIC             = 'missing_arabic';
    public const TYPE_MISSING_IMAGES             = 'missing_images';
    public const TYPE_HIGH_VIEW_LOW_CONVERSION   = 'high_view_low_conversion';
    public const TYPE_WISHLIST_INTEREST          = 'wishlist_interest';
    public const TYPE_CART_ABANDONMENT           = 'cart_abandonment';
    public const TYPE_PROMOTION_OPPORTUNITY      = 'promotion_opportunity';
    public const TYPE_MISSING_STORE_PROFILE      = 'missing_store_profile';

    // Phase 11B.4 v11B.4.2 Defect 6 fix — variant alert types.
    // ProductVariant has independent `stock` + `is_active` columns
    // (see database/migrations/*product_variants*), so variant-level
    // stock alerts ARE applicable and produced by InventoryAlertService.
    public const TYPE_VARIANT_OUT_OF_STOCK          = 'variant_out_of_stock';
    public const TYPE_VARIANT_LOW_STOCK             = 'variant_low_stock';
    public const TYPE_VARIANT_FAST_MOVING_LOW_STOCK = 'variant_fast_moving_low_stock';

    // Phase 11B.4 v11B.4.2 Defect 7 — search-demand suggestion.
    // Only produced when siteSettings has search_queries data AND the
    // vendor's category overlap for popular terms is thin.
    public const TYPE_SEARCH_DEMAND = 'search_demand';

    // Priorities (§8)
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_MEDIUM   = 'medium';
    public const PRIORITY_LOW      = 'low';
    public const PRIORITY_INFO     = 'info';

    // Statuses (§32)
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_DISMISSED  = 'dismissed';
    public const STATUS_SNOOZED    = 'snoozed';
    public const STATUS_RESOLVED   = 'resolved';
    public const STATUS_EXPIRED    = 'expired';

    // Critical alerts that cannot be permanently dismissed (§17 §32)
    public const NON_DISMISSABLE_TYPES = [
        self::TYPE_OUT_OF_STOCK,
        self::TYPE_FAST_MOVING_LOW_STOCK,
        self::TYPE_VARIANT_OUT_OF_STOCK,
        self::TYPE_VARIANT_FAST_MOVING_LOW_STOCK,
    ];

    // Statuses that are considered "still open" and get an
    // active_dedupe_key. Resolved/expired stay NULL (see migration
    // 2026_12_01 for the rationale).
    public const OPEN_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SNOOZED,
        self::STATUS_DISMISSED,
    ];

    /**
     * Phase 11B.4 v11B.4.2 Defect 5 fix — deterministic dedupe key.
     * Used both by the UNIQUE index and by Manager::materializeAlerts.
     */
    public static function buildDedupeKey(int $vendorId, string $type, ?string $entityType, ?int $entityId): string
    {
        return sprintf(
            'vendor:%d|type:%s|entity:%s:%s',
            $vendorId,
            $type,
            (string) ($entityType ?? '-'),
            (string) ($entityId ?? '-'),
        );
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForVendor(Builder $q, int $vendorId): Builder
    {
        return $q->where('vendor_id', $vendorId);
    }

    public function scopeVisible(Builder $q): Builder
    {
        // Not dismissed / resolved / expired; and (not snoozed OR snooze past)
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_SNOOZED])
                 ->where(fn ($q2) => $q2->where('status', self::STATUS_ACTIVE)
                                        ->orWhere(function ($q3) {
                                            $q3->where('status', self::STATUS_SNOOZED)
                                               ->whereNotNull('expires_at')
                                               ->where('expires_at', '<=', now());
                                        }));
    }
}
