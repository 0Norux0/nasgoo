<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCommissionRule extends Model
{
    use HasFactory;

    public const SCOPE_GLOBAL           = 'global';
    public const SCOPE_VENDOR           = 'vendor';
    public const SCOPE_PACKAGE          = 'package';
    public const SCOPE_CATEGORY         = 'category';
    public const SCOPE_PRODUCT          = 'product';
    public const SCOPE_SERVICE_CATEGORY = 'service_category';
    public const SCOPE_SERVICE          = 'service';

    public const TYPE_PERCENT           = 'percent';
    public const TYPE_FIXED             = 'fixed';
    public const TYPE_FIXED_PLUS_PERCENT = 'fixed_plus_percent';

    protected $fillable = [
        'vendor_id', 'scope', 'scope_id',
        'product_type', 'payment_method', 'calculation_base',
        'commission_type', 'percent_value', 'fixed_value_minor', 'currency',
        'priority', 'effective_from', 'effective_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percent_value'     => 'decimal:4',
            'fixed_value_minor' => 'integer',
            'priority'          => 'integer',
            'effective_from'    => 'datetime',
            'effective_until'   => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    /** @return BelongsTo<Vendor, VendorCommissionRule> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isEffectiveAt(\DateTimeInterface $when): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->effective_from && $this->effective_from > $when) {
            return false;
        }
        if ($this->effective_until && $this->effective_until < $when) {
            return false;
        }
        return true;
    }
}
