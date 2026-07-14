<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'product_id', 'user_id', 'order_item_id',
        'rating', 'title', 'body',
        'status', 'is_verified_purchase',
        'rejection_reason', 'approved_at', 'rejected_at',
        // Phase 9 — vendor response + simple image gallery
        'vendor_response', 'vendor_responded_at', 'images',
    ];

    protected $casts = [
        'rating'               => 'integer',
        'is_verified_purchase' => 'boolean',
        'approved_at'          => 'datetime',
        'rejected_at'          => 'datetime',
        // Phase 9
        'vendor_responded_at'  => 'datetime',
        'images'               => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Scope: approved reviews only (public-visible). */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
