<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_GRACE     = 'grace';
    public const STATUS_PENDING   = 'pending';

    protected $fillable = [
        'vendor_id', 'vendor_package_id',
        'starts_at', 'ends_at',
        'status', 'auto_renew',
        'amount_paid_minor', 'currency', 'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'         => 'datetime',
            'ends_at'           => 'datetime',
            'auto_renew'        => 'boolean',
            'amount_paid_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Vendor, VendorSubscription> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<VendorPackage, VendorSubscription> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(VendorPackage::class, 'vendor_package_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
