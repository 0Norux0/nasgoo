<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID     = 'paid';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_OTHER         = 'other';

    protected $fillable = [
        'vendor_id', 'requested_amount_minor', 'currency',
        'status', 'payout_method', 'payout_details',
        'admin_notes', 'rejection_reason', 'transfer_reference',
        'processed_by',
        'requested_at', 'approved_at', 'rejected_at', 'paid_at',
    ];

    protected $casts = [
        'payout_details' => 'array',
        'requested_at'   => 'datetime',
        'approved_at'    => 'datetime',
        'rejected_at'    => 'datetime',
        'paid_at'        => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** Amounts that are NOT yet paid (still reserve available balance). */
    public function scopeReservedForBalance($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
    public function isPaid(): bool     { return $this->status === self::STATUS_PAID; }
}
