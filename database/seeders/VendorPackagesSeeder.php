<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VendorPackage;
use Illuminate\Database\Seeder;

class VendorPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'        => 'Basic Vendor',
                'slug'        => 'basic',
                'description' => 'Get started with the essentials. Best for individual sellers and small businesses.',
                'price_minor' => 0,
                'currency'    => 'KWD',
                'billing_cycle' => 'monthly',
                'trial_days'  => 0,

                'max_products' => 25,
                'max_services' => null,           // services not allowed
                'max_images_per_product' => 3,

                'allow_video'             => false,
                'allow_3d'                => false,
                'allow_dropshipping'      => false,  // admin can override per-vendor
                'allow_product_import'    => false,
                'allow_customization'     => false,
                'allow_services'          => false,
                'allow_promotions'        => false,
                'allow_deal_of_day'       => false,
                'allow_featured_vendor'   => false,

                'analytics_level'                  => 'basic',
                'default_admin_commission_percent' => 30.00,
                'is_active'                        => true,
                'sort_order'                       => 1,
            ],
            [
                'name'        => 'Standard Vendor',
                'slug'        => 'standard',
                'description' => 'For growing businesses with richer media and service offerings.',
                'price_minor' => 5_000,           // 5.000 KWD/month
                'currency'    => 'KWD',
                'billing_cycle' => 'monthly',
                'trial_days'  => 14,

                'max_products' => 200,
                'max_services' => 25,
                'max_images_per_product' => 6,

                'allow_video'             => true,
                'allow_3d'                => false,
                'allow_dropshipping'      => false,
                'allow_product_import'    => true,
                'allow_customization'     => false,
                'allow_services'          => true,
                'allow_promotions'        => true,
                'allow_deal_of_day'       => false,
                'allow_featured_vendor'   => false,

                'analytics_level'                  => 'standard',
                'default_admin_commission_percent' => 20.00,
                'is_active'                        => true,
                'sort_order'                       => 2,
            ],
            [
                'name'        => 'Professional Vendor',
                'slug'        => 'professional',
                'description' => 'Full feature set: video, 3D, dropshipping, customization, featured eligibility.',
                'price_minor' => 25_000,          // 25.000 KWD/month
                'currency'    => 'KWD',
                'billing_cycle' => 'monthly',
                'trial_days'  => 30,

                'max_products' => null,           // unlimited
                'max_services' => null,
                'max_images_per_product' => 10,

                'allow_video'             => true,
                'allow_3d'                => true,
                'allow_dropshipping'      => true,
                'allow_product_import'    => true,
                'allow_customization'     => true,
                'allow_services'          => true,
                'allow_promotions'        => true,
                'allow_deal_of_day'       => true,
                'allow_featured_vendor'   => true,

                'analytics_level'                  => 'advanced',
                'default_admin_commission_percent' => 10.00,
                'is_active'                        => true,
                'sort_order'                       => 3,
            ],
        ];

        foreach ($packages as $p) {
            VendorPackage::updateOrCreate(['slug' => $p['slug']], $p);
        }

        $this->command?->info('Seeded 3 vendor packages: Basic / Standard / Professional.');
    }
}
