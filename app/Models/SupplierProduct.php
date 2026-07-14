<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_id
 * @property int $supplier_platform_id
 * @property ?int $supplier_integration_id
 * @property ?int $product_id
 * @property ?string $external_product_id
 * @property ?string $external_sku
 * @property ?string $source_url
 * @property string $title
 * @property ?string $description
 * @property ?array $images
 * @property int $supplier_cost_minor
 * @property string $supplier_currency
 * @property string $supplier_stock_status
 * @property ?int $supplier_stock_qty
 * @property int $supplier_shipping_minor
 * @property ?int $estimated_delivery_days
 * @property ?array $raw_payload
 * @property string $import_status
 */
class SupplierProduct extends Model
{
    use HasFactory;

    public const STATUS_PENDING       = 'pending';
    public const STATUS_MAPPED        = 'mapped';
    public const STATUS_PUBLISHED     = 'published';
    public const STATUS_REJECTED      = 'rejected';
    public const STATUS_DISCONTINUED  = 'discontinued';

    public const STOCK_IN     = 'in_stock';
    public const STOCK_OUT    = 'out_of_stock';
    public const STOCK_UNKNOWN = 'unknown';

    protected $fillable = [
        'vendor_id', 'supplier_platform_id', 'supplier_integration_id', 'product_id',
        'external_product_id', 'external_sku', 'source_url',
        'title', 'description', 'images',
        'supplier_cost_minor', 'supplier_currency',
        'supplier_stock_status', 'supplier_stock_qty', 'supplier_shipping_minor',
        'estimated_delivery_days',
        'raw_payload',
        'import_status', 'import_notes',
        'imported_at', 'mapped_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'images'                  => 'array',
            'raw_payload'             => 'array',
            'supplier_cost_minor'     => 'integer',
            'supplier_stock_qty'      => 'integer',
            'supplier_shipping_minor' => 'integer',
            'estimated_delivery_days' => 'integer',
            'imported_at'             => 'datetime',
            'mapped_at'                => 'datetime',
            'published_at'             => 'datetime',
        ];
    }

    public function vendor(): BelongsTo           { return $this->belongsTo(Vendor::class); }
    public function platform(): BelongsTo         { return $this->belongsTo(SupplierPlatform::class, 'supplier_platform_id'); }
    public function integration(): BelongsTo      { return $this->belongsTo(SupplierIntegration::class, 'supplier_integration_id'); }
    public function product(): BelongsTo          { return $this->belongsTo(Product::class); }

    public function scopeForVendor($q, int $vendorId) { return $q->where('vendor_id', $vendorId); }
}
