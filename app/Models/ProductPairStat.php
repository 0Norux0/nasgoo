<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.2 — co-occurrence stat for an unordered pair of products.
 * The (product_a_id, product_b_id) tuple is canonically ordered with
 * product_a_id < product_b_id at write time so we never store (A,B) and
 * (B,A) separately.
 */
class ProductPairStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_a_id', 'product_b_id',
        'pair_count', 'distinct_customer_count',
        'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'pair_count'              => 'integer',
            'distinct_customer_count' => 'integer',
            'first_seen_at'           => 'datetime',
            'last_seen_at'            => 'datetime',
        ];
    }

    public function productA(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_a_id');
    }

    public function productB(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_b_id');
    }

    /**
     * Canonical ordering: returns [min, max] so callers can unambiguously
     * upsert without checking which side is which.
     *
     * @return array{0:int,1:int}
     */
    public static function canonical(int $aId, int $bId): array
    {
        return $aId < $bId ? [$aId, $bId] : [$bId, $aId];
    }
}
