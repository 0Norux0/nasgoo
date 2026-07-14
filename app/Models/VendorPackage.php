<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VendorPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description',
        'price_minor', 'currency', 'billing_cycle', 'trial_days',
        'max_products', 'max_services', 'max_images_per_product',
        'allow_video', 'allow_3d', 'allow_dropshipping', 'allow_product_import',
        'allow_customization', 'allow_services', 'allow_promotions',
        'allow_deal_of_day', 'allow_featured_vendor',
        'analytics_level',
        'default_admin_commission_percent',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor'                       => 'integer',
            'trial_days'                        => 'integer',
            'max_products'                      => 'integer',
            'max_services'                      => 'integer',
            'max_images_per_product'            => 'integer',
            'allow_video'                       => 'boolean',
            'allow_3d'                          => 'boolean',
            'allow_dropshipping'                => 'boolean',
            'allow_product_import'              => 'boolean',
            'allow_customization'               => 'boolean',
            'allow_services'                    => 'boolean',
            'allow_promotions'                  => 'boolean',
            'allow_deal_of_day'                 => 'boolean',
            'allow_featured_vendor'             => 'boolean',
            'is_active'                         => 'boolean',
            'sort_order'                        => 'integer',
            'default_admin_commission_percent'  => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VendorPackage $package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });
    }

    /** @return HasMany<VendorSubscription> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }

    /**
     * Return all feature flags as a name → bool map for UI / dashboard.
     * @return array<string, bool>
     */
    public function featureFlags(): array
    {
        return [
            'video'              => $this->allow_video,
            'three_d'            => $this->allow_3d,
            'dropshipping'       => $this->allow_dropshipping,
            'product_import'     => $this->allow_product_import,
            'customization'      => $this->allow_customization,
            'services'           => $this->allow_services,
            'promotions'         => $this->allow_promotions,
            'deal_of_day'        => $this->allow_deal_of_day,
            'featured_vendor'    => $this->allow_featured_vendor,
        ];
    }
}
