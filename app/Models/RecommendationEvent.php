<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.2 §21 — privacy-safe recommendation analytics row.
 *
 * No PII stored beyond session_token (SHA-256 hash, NOT raw session ID)
 * and user_id (for attribution joins ONLY — never displayed in reports).
 */
class RecommendationEvent extends Model
{
    use HasFactory;

    public const TYPE_IMPRESSION  = 'impression';
    public const TYPE_CLICK       = 'click';
    public const TYPE_ADD_TO_CART = 'add_to_cart';
    public const TYPE_PURCHASE    = 'purchase';

    public const TYPES = [
        self::TYPE_IMPRESSION, self::TYPE_CLICK, self::TYPE_ADD_TO_CART, self::TYPE_PURCHASE,
    ];

    protected $fillable = [
        'event_type', 'product_id', 'recommended_product_id',
        'recommendation_type', 'locale', 'device_category',
        'session_token', 'user_id',
        // Phase 11B.2 v11B.2.1 — purchase attribution columns
        'order_item_id', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recommendedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'recommended_product_id');
    }

    /**
     * Hash a raw session ID into the storable session_token. Used by the
     * analytics service to anonymize the trail.
     */
    public static function hashSession(?string $rawSessionId): ?string
    {
        if (! $rawSessionId) {
            return null;
        }
        return hash('sha256', $rawSessionId);
    }
}
