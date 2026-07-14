<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int $vendor_id
 * @property int $supplier_platform_id
 * @property int $order_id
 * @property ?int $supplier_product_id
 * @property string $status
 * @property ?string $supplier_reference
 * @property ?string $tracking_number
 * @property ?string $tracking_url
 * @property ?string $carrier
 * @property int $supplier_cost_minor
 * @property int $supplier_shipping_minor
 * @property int $total_minor
 * @property string $currency
 */
class SupplierOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PLACED    = 'placed';
    public const STATUS_PACKED    = 'packed';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';

    public const ALL_STATUSES = [
        self::STATUS_PENDING, self::STATUS_PLACED, self::STATUS_PACKED,
        self::STATUS_SHIPPED, self::STATUS_DELIVERED,
        self::STATUS_CANCELLED, self::STATUS_FAILED, self::STATUS_REFUNDED,
    ];

    protected $fillable = [
        'number', 'vendor_id', 'supplier_platform_id', 'order_id', 'supplier_product_id',
        'status', 'supplier_reference', 'tracking_number', 'tracking_url', 'carrier',
        'supplier_cost_minor', 'supplier_shipping_minor', 'total_minor', 'currency',
        'placed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost_minor'     => 'integer',
            'supplier_shipping_minor' => 'integer',
            'total_minor'             => 'integer',
            'placed_at'                => 'datetime',
            'shipped_at'               => 'datetime',
            'delivered_at'             => 'datetime',
            'cancelled_at'             => 'datetime',
        ];
    }

    public function vendor(): BelongsTo            { return $this->belongsTo(Vendor::class); }
    public function platform(): BelongsTo          { return $this->belongsTo(SupplierPlatform::class, 'supplier_platform_id'); }
    public function order(): BelongsTo             { return $this->belongsTo(Order::class); }
    public function supplierProduct(): BelongsTo   { return $this->belongsTo(SupplierProduct::class); }
    public function orderItems(): HasMany          { return $this->hasMany(OrderItem::class, 'supplier_order_id'); }
    public function events(): HasMany              { return $this->hasMany(SupplierOrderEvent::class)->orderBy('id'); }

    public function scopeForVendor($q, int $vendorId) { return $q->where('vendor_id', $vendorId); }
}
