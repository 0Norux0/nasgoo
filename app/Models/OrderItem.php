<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    public const FUL_UNFULFILLED = 'unfulfilled';
    public const FUL_FULFILLED   = 'fulfilled';
    public const FUL_RETURNED    = 'returned';

    protected $fillable = [
        'order_id', 'vendor_id', 'product_id', 'variant_id', 'supplier_order_id',
        'product_name', 'product_sku', 'variant_name', 'variant_attributes',
        'quantity', 'unit_price_minor', 'line_total_minor', 'currency',
        'commission_percent', 'commission_amount_minor', 'vendor_earning_minor',
        'supplier_cost_minor',
        'customization_fee_minor', 'customization_status',
        'fulfillment_status',
        // Phase 9 v9.3 — per-line coupon discount allocation
        'coupon_allocation_minor',
        // Phase 10 v10.8 — promotion snapshot per line
        'promotion_id', 'promotion_name', 'promotion_discount_minor', 'original_unit_price_minor',
    ];

    protected function casts(): array
    {
        return [
            'variant_attributes'        => 'array',
            'quantity'                  => 'integer',
            'unit_price_minor'          => 'integer',
            'line_total_minor'          => 'integer',
            'commission_percent'        => 'decimal:2',
            'commission_amount_minor'   => 'integer',
            'vendor_earning_minor'      => 'integer',
            'supplier_cost_minor'       => 'integer',
            'customization_fee_minor'   => 'integer',
            // Phase 9 v9.3
            'coupon_allocation_minor'   => 'integer',
        ];
    }

    // Phase 7 — customization status state machine. Orthogonal to fulfillment_status.
    public const CUST_PENDING            = 'pending';
    public const CUST_IN_REVIEW          = 'in_review';
    public const CUST_PROOF_UPLOADED     = 'proof_uploaded';
    public const CUST_CUSTOMER_APPROVED  = 'customer_approved';
    public const CUST_CUSTOMER_REJECTED  = 'customer_rejected';
    public const CUST_IN_PRODUCTION      = 'in_production';
    public const CUST_COMPLETED          = 'completed';

    public const ALL_CUSTOMIZATION_STATUSES = [
        self::CUST_PENDING, self::CUST_IN_REVIEW, self::CUST_PROOF_UPLOADED,
        self::CUST_CUSTOMER_APPROVED, self::CUST_CUSTOMER_REJECTED,
        self::CUST_IN_PRODUCTION, self::CUST_COMPLETED,
    ];

    /** @return BelongsTo<Order, OrderItem> */
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** @return BelongsTo<Vendor, OrderItem> */
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    /** @return BelongsTo<Product, OrderItem> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** Phase 5 — reviews linked to this specific purchased line. */
    public function reviews(): HasMany { return $this->hasMany(ProductReview::class); }

    /** @return BelongsTo<ProductVariant, OrderItem> */
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }

    /** Phase 6 — dropshipping link */
    public function supplierOrder(): BelongsTo { return $this->belongsTo(SupplierOrder::class); }

    // Phase 7 — customization data + proofs
    public function customizations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemCustomization::class);
    }
    public function proofs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomizationProof::class)->orderByDesc('id');
    }
    public function latestProof(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomizationProof::class)->latestOfMany();
    }
}
