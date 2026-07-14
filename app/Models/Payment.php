<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING            = 'pending';
    public const STATUS_AUTHORIZED         = 'authorized';
    public const STATUS_CAPTURED           = 'captured';
    public const STATUS_FAILED             = 'failed';
    public const STATUS_REFUNDED           = 'refunded';
    public const STATUS_PARTIAL_REFUND     = 'partially_refunded';
    public const STATUS_CANCELLED          = 'cancelled';

    protected $fillable = [
        'order_id', 'payment_method_id', 'method_slug', 'provider',
        'status', 'amount_minor', 'currency', 'refunded_minor',
        'external_id', 'reference', 'metadata', 'failure_reason',
        'authorized_at', 'captured_at', 'failed_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor'    => 'integer',
            'refunded_minor'  => 'integer',
            'metadata'        => 'array',
            'authorized_at'   => 'datetime',
            'captured_at'     => 'datetime',
            'failed_at'       => 'datetime',
            'refunded_at'     => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, Payment> */
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** @return BelongsTo<PaymentMethod, Payment> */
    public function method(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }

    /** @return HasMany<PaymentTransaction> */
    public function transactions(): HasMany { return $this->hasMany(PaymentTransaction::class); }

    public function isCaptured(): bool { return $this->status === self::STATUS_CAPTURED; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
}
