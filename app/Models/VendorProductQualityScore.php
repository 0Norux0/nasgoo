<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.4 §9 §30 — per-product quality score (0-100).
 */
class VendorProductQualityScore extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'score' => 'integer',
        'missing_fields' => 'array',
        'score_breakdown' => 'array',
        'computed_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
