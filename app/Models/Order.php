<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    // Overall lifecycle
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_CONFIRMED       = 'confirmed';
    public const STATUS_SHIPPED         = 'shipped';
    public const STATUS_DELIVERED       = 'delivered';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_REFUNDED        = 'refunded';
    public const STATUS_FAILED          = 'failed';

    // Payment status
    public const PAY_PENDING            = 'pending';
    public const PAY_AUTHORIZED         = 'authorized';
    public const PAY_PAID               = 'paid';
    public const PAY_FAILED             = 'failed';
    public const PAY_REFUNDED           = 'refunded';
    public const PAY_PARTIAL_REFUND     = 'partially_refunded';

    // Fulfillment status
    public const FUL_UNFULFILLED         = 'unfulfilled';
    public const FUL_PARTIAL             = 'partially_fulfilled';
    public const FUL_FULFILLED           = 'fulfilled';
    public const FUL_RETURNED            = 'returned';

    protected $fillable = [
        'number', 'user_id',
        'status', 'payment_status', 'fulfillment_status',
        'currency',
        'shipping_method_id', 'shipping_method_name',  // Phase 5
        'subtotal_minor', 'shipping_minor', 'tax_minor', 'discount_minor', 'total_minor',
        'platform_commission_minor', 'vendor_earnings_minor',
        'customer_notes', 'internal_notes',
        'paid_at', 'confirmed_at', 'shipped_at', 'delivered_at', 'completed_at',
        'cancelled_at', 'cancellation_reason',
        'earnings_release_at', 'earnings_released',
        // Phase 9 — coupon snapshot
        'coupon_id', 'coupon_discount_minor', 'coupon_code',
        // Phase 10 v10.8 — promotion snapshot (sum of per-line promotion discounts)
        'promotion_discount_minor',
    ];

    protected function casts(): array
    {
        return [
            'paid_at'                   => 'datetime',
            'confirmed_at'              => 'datetime',
            'shipped_at'                => 'datetime',
            'delivered_at'              => 'datetime',
            'completed_at'              => 'datetime',
            'cancelled_at'              => 'datetime',
            'earnings_release_at'       => 'datetime',
            'earnings_released'         => 'boolean',
            'subtotal_minor'            => 'integer',
            'shipping_minor'            => 'integer',
            'tax_minor'                 => 'integer',
            'discount_minor'            => 'integer',
            'total_minor'               => 'integer',
            'platform_commission_minor' => 'integer',
            'vendor_earnings_minor'     => 'integer',
            // Phase 9
            'coupon_discount_minor'     => 'integer',
        ];
    }

    /** @return BelongsTo<User, Order> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** @return HasMany<OrderItem> */
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }

    /** @return HasMany<OrderAddress> */
    public function addresses(): HasMany { return $this->hasMany(OrderAddress::class); }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    /** @return HasMany<OrderEvent> */
    public function events(): HasMany { return $this->hasMany(OrderEvent::class)->orderBy('id'); }

    /** @return HasMany<Payment> */
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    // Phase 5 — chosen shipping method (snapshot on order)
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /* ─────────── State helpers ─────────── */

    public function isPaid(): bool       { return $this->payment_status === self::PAY_PAID; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }

    public function vendorIds(): array
    {
        return $this->items()->pluck('vendor_id')->unique()->values()->all();
    }

    /* ─────────── Scopes ─────────── */

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForVendor(Builder $q, int $vendorId): Builder
    {
        return $q->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'fulfillment_status', 'total_minor'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('order');
    }
}
