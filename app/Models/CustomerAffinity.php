<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.3 §14 — precomputed customer affinity score along a dimension
 * (category / vendor / price_band). Read during homepage rendering only.
 */
class CustomerAffinity extends Model
{
    public const DIM_CATEGORY   = 'category';
    public const DIM_VENDOR     = 'vendor';
    public const DIM_PRICE_BAND = 'price_band';

    protected $fillable = [
        'user_id', 'dimension', 'dimension_id', 'dimension_key',
        'score', 'signal_count', 'last_signal_at',
    ];

    protected function casts(): array
    {
        return [
            'last_signal_at' => 'datetime',
            'score'          => 'integer',
            'signal_count'   => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
