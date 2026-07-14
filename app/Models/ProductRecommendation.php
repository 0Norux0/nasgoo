<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.2 — one precomputed recommendation row.
 */
class ProductRecommendation extends Model
{
    use HasFactory;

    public const TYPE_SIMILAR         = 'similar';
    public const TYPE_FBT             = 'fbt';
    public const TYPE_ALSO_BOUGHT     = 'also_bought';
    public const TYPE_SIMILAR_SERVICE = 'similar_service';

    public const TYPES = [
        self::TYPE_SIMILAR, self::TYPE_FBT, self::TYPE_ALSO_BOUGHT, self::TYPE_SIMILAR_SERVICE,
    ];

    protected $fillable = [
        'product_id', 'recommended_product_id', 'recommendation_type',
        'score', 'evidence_count', 'confidence', 'generated_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'score'          => 'float',
            'evidence_count' => 'integer',
            'confidence'     => 'float',
            'generated_at'   => 'datetime',
            'expires_at'     => 'datetime',
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
}
