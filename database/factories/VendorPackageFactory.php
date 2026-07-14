<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VendorPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorPackage>
 */
class VendorPackageFactory extends Factory
{
    protected $model = VendorPackage::class;

    public function definition(): array
    {
        $name = fake()->unique()->word() . ' Package';
        return [
            'name'                              => $name,
            'slug'                              => Str::slug($name) . '-' . Str::random(4),
            'description'                       => fake()->sentence(),
            'price_minor'                       => fake()->numberBetween(0, 50_000),
            'currency'                          => 'KWD',
            'billing_cycle'                     => 'monthly',
            'trial_days'                        => 0,
            'max_products'                      => fake()->numberBetween(10, 1000),
            'max_images_per_product'            => 5,
            'allow_video'                       => false,
            'allow_3d'                          => false,
            'allow_dropshipping'                => false,
            'allow_product_import'              => false,
            'allow_customization'               => false,
            'allow_services'                    => false,
            'allow_promotions'                  => false,
            'allow_deal_of_day'                 => false,
            'allow_featured_vendor'             => false,
            'analytics_level'                   => 'basic',
            'default_admin_commission_percent'  => 20.00,
            'is_active'                         => true,
            'sort_order'                        => 0,
        ];
    }

    public function basic(): static
    {
        return $this->state(fn () => [
            'name' => 'Basic Test',
            'slug' => 'basic-test',
            'price_minor' => 0,
            'default_admin_commission_percent' => 30.00,
        ]);
    }
}
