<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.4 — vendor intelligence summary (one row per vendor).
 * Refreshed by the vendor-intelligence:generate command.
 */
class VendorIntelligenceSummary extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'total_products' => 'integer',
        'total_active_products' => 'integer',
        'out_of_stock_count' => 'integer',
        'low_stock_count' => 'integer',
        'slow_moving_count' => 'integer',
        'missing_arabic_count' => 'integer',
        'missing_images_count' => 'integer',
        'active_alerts_count' => 'integer',
        'store_completion_score' => 'integer',
        'avg_product_quality' => 'integer',
        'computed_at' => 'datetime',
        // Phase 11B.4 v11B.4.2 Defect 11 fix — stale marking columns.
        'stale_at' => 'datetime',
        'last_generated_at' => 'datetime',
        // Phase 11B.4 v11B.4.3 Fix 2 — email digest columns.
        'last_digest_sent_at' => 'datetime',
        'email_opted_out' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
