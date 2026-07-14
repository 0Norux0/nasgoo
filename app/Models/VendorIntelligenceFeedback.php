<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.4 §17 — vendor's dismiss/snooze feedback for a suggestion.
 */
class VendorIntelligenceFeedback extends Model
{
    protected $table = 'vendor_intelligence_feedback';
    protected $guarded = ['id'];
    protected $casts = [
        'snoozed_until' => 'datetime',
        'dismissed_at'  => 'datetime',
        'metadata'      => 'array',
    ];

    public const ACTION_DISMISSED = 'dismissed';
    public const ACTION_SNOOZED   = 'snoozed';

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
