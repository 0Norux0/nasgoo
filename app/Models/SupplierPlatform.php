<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ?string $logo_path
 * @property ?string $website_url
 * @property string $integration_type      manual | csv | api | feed
 * @property string $default_currency
 * @property ?int $default_delivery_days
 * @property bool $is_active
 * @property ?string $notes
 */
class SupplierPlatform extends Model
{
    use HasFactory;

    public const TYPE_MANUAL = 'manual';
    public const TYPE_CSV    = 'csv';
    public const TYPE_API    = 'api';
    public const TYPE_FEED   = 'feed';

    protected $fillable = [
        'name', 'slug', 'logo_path', 'website_url',
        'integration_type', 'default_currency', 'default_delivery_days',
        'is_active', 'notes', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_delivery_days' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function integrations(): HasMany { return $this->hasMany(SupplierIntegration::class); }
    public function supplierProducts(): HasMany { return $this->hasMany(SupplierProduct::class); }
    public function supplierOrders(): HasMany { return $this->hasMany(SupplierOrder::class); }
}
