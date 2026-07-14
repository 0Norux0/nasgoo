<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.3 §8 — per-customer + per-guest-session product-view record.
 */
class CustomerProductView extends Model
{
    protected $fillable = [
        'user_id', 'session_key', 'product_id',
        'locale', 'device_category', 'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
