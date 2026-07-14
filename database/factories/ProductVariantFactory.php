<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id'        => Product::factory()->variable(),
            'sku'               => strtoupper(Str::random(8)),
            'name'              => 'Default',
            'price_minor'       => $this->faker->numberBetween(100, 50000),
            'currency'          => 'KWD',
            'stock'             => $this->faker->numberBetween(0, 50),
            'attribute_values'  => null,
            'position'          => 0,
            'is_active'         => true,
        ];
    }
}
